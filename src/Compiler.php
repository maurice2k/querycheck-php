<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck;

use Maurice2k\QueryCheck\Exception\RegexError;
use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\SyntaxError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;

/**
 * Compiles a query into a tree of closures.
 *
 * Everything that only depends on the query is decided here, once: which
 * operator a key is, shorthand forms like {age: 37}, field path parsing,
 * operator arity and the type of a literal operand. The returned closures
 * only look at the document.
 *
 * Three kinds of closures are built:
 *
 * - query nodes:      Closure(array $data): bool             ($and, $or, fields, $expr)
 * - field operators:  Closure(mixed $value, array $data): bool ($eq, $gt, $regex, ...)
 * - aggregation nodes: Closure(array $data): mixed            (everything inside $expr)
 *
 * The settings are fixed for the lifetime of a compiler. QueryCheck creates a
 * new one, and recompiles, whenever a setting changes.
 *
 * The closures reference this compiler but the compiler never references the
 * closures, so there is no reference cycle and a QueryCheck instance is freed
 * as soon as it goes out of scope.
 *
 * @internal used by QueryCheck, not part of the public API
 */
final class Compiler
{
    /**
     * Query operators. Used to tell {age: {$gt: 1}} (operator expression)
     * apart from {address: {city: "Berlin"}} (object compared with $eq).
     */
    private const QUERY_OPERATORS = [
        '$eq' => true,
        '$ne' => true,
        '$gt' => true,
        '$gte' => true,
        '$lt' => true,
        '$lte' => true,
        '$in' => true,
        '$regex' => true,
        '$options' => true,
        '$not' => true,
    ];

    /**
     * Where the compiler currently is in the query, for error messages, e.g.
     * ['$or', 1, 'age', '$gt']. A step is only removed after it compiled, so
     * when a SyntaxError is thrown the path still points to its location.
     * Only recorded while $track is on.
     *
     * @var list<string|int>
     */
    private array $path = [];

    /**
     * Whether $path is recorded. Off for a normal compile, which then costs
     * nothing extra; compileQuery() compiles a second time with it on only to
     * find the location of a SyntaxError. Always on with an operand
     * evaluator, because parts compiled later need the location of their
     * place in the query.
     */
    private bool $track;

    public function __construct(
        private readonly bool $strictMode,
        private readonly bool $throwOnUnknownVariable,
        private readonly ?\Closure $operandEvaluator,
    ) {
        $this->track = $operandEvaluator !== null;
    }

    /**
     * Compiles a whole query. A SyntaxError gets the location of the error
     * as a prefix, e.g. "$or[1].age.$gtt: Unsupported query operator: $gtt".
     *
     * @return \Closure(array<mixed>): bool
     */
    public function compileQuery(mixed $query): \Closure
    {
        try {
            return $this->compileAt([], fn(): \Closure => $this->compileQueryNode($query));
        } catch (SyntaxError $e) {
            if ($this->track) {
                throw $e; // already has its location
            }
        }

        // Compiling has no side effects, so a second pass that records the
        // path fails at the same place, this time with the location.
        $this->track = true;
        try {
            $this->compileAt([], fn(): \Closure => $this->compileQueryNode($query));
        } finally {
            $this->track = false;
        }
        throw $e; // not reached: the second pass throws
    }

    /**
     * Runs a compile step starting at $at and adds the location to a
     * SyntaxError. Also used for parts that are compiled later, at runtime
     * (with an operand evaluator).
     *
     * @template T
     * @param list<string|int> $at
     * @param \Closure(): T $compile
     * @return T
     */
    private function compileAt(array $at, \Closure $compile): mixed
    {
        $this->path = $at;
        try {
            $result = $compile();
        } catch (SyntaxError $e) {
            $location = self::formatPath($this->path);
            $this->path = [];
            throw $location === '' ? $e : new SyntaxError("{$location}: {$e->getMessage()}", 0, $e);
        }
        $this->path = [];
        return $result;
    }

    /**
     * @param list<string|int> $path
     */
    private static function formatPath(array $path): string
    {
        $out = '';
        foreach ($path as $segment) {
            $out .= is_int($segment) ? "[{$segment}]" : ($out === '' ? $segment : ".{$segment}");
        }
        return $out;
    }

    /**
     * Splits a field path like "items[0].name" into segments, once.
     *
     * Each segment is [isIndex, key]: [true, 0] for "[0]" and [false, "name"]
     * for "name". An escaped dot ("a\.b") stays part of the key, as before.
     *
     * @return list<array{bool, int|string}>
     */
    public static function parsePath(string $variableName): array
    {
        if ($variableName !== '' && strpbrk($variableName, '.[\\') === false) {
            // a plain field name like "age"
            return [[false, $variableName]];
        }

        if (strpbrk($variableName, '[\\') === false) {
            // No index and no escaped dot: the regex below reduces to the
            // non-empty runs between dots.
            $segments = [];
            foreach (explode('.', $variableName) as $part) {
                if ($part !== '') {
                    $segments[] = [false, $part];
                }
            }
            return $segments;
        }

        preg_match_all('/(\\\.|[^.]+?)+/', str_replace('[', '.[', $variableName), $matches);

        $segments = [];
        foreach ($matches[0] as $part) {
            if (preg_match('/\[(\d+)\]$/', $part, $indexMatch)) {
                $segments[] = [true, (int)$indexMatch[1]];
            } else {
                $segments[] = [false, $part];
            }
        }

        return $segments;
    }

    // ------------------------------------------------------------------
    // Query nodes: Closure(array $data): bool
    // ------------------------------------------------------------------

    /**
     * @return \Closure(array<mixed>): bool
     */
    private function compileQueryNode(mixed $query): \Closure
    {
        if (!is_array($query)) {
            throw new SyntaxError('Query must be an object: ' . json_encode($query));
        }

        $count = count($query);

        if ($count === 0) {
            // empty query is always valid
            return static fn(array $data): bool => true;
        }

        if ($count === 1) {
            $key = array_key_first($query);
            return $this->compileEntry($key, $query[$key]);
        }

        // implicit $and: {age: 37, name: "John"} is
        // {$and: [{age: 37}, {name: "John"}]}
        $children = [];
        foreach ($query as $key => $value) {
            $children[] = $this->compileEntry($key, $value);
        }

        return self::all($children);
    }

    /**
     * One {key: value} entry of a query object.
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileEntry(int|string $key, mixed $value): \Closure
    {
        if ($this->track) {
            $this->path[] = $key;
        }

        if (!is_string($key)) {
            throw new SyntaxError("Field names must be strings, got: {$key}");
        }

        if ($key === '') {
            throw new SyntaxError('Empty keys are not supported!');
        }

        $closure = $key[0] === '$'
            // "key" must be a boolean operator like $and/$or, "value" the
            // sub queries
            ? match ($key) {
                '$or' => $this->compileOr($value),
                '$and' => $this->compileAnd($value),
                '$expr' => $this->compileExpr($value),
                default => throw new SyntaxError("Unsupported boolean operator: {$key}"),
            }
            // "key" is a variable name; "value" the expression (or a set of
            // expressions) to evaluate against it
            : $this->compileField($key, $value);

        if ($this->track) {
            array_pop($this->path);
        }
        return $closure;
    }

    /**
     * Evaluates the $or logical operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/or/
     *
     * Syntax: { $or: [ <expression1>, <expression2>, ... ] }
     *
     * Short-circuits like $and: evaluation stops at the first matching branch,
     * in the order given, so later branches are not evaluated (and cannot
     * throw) once one matched. MongoDB's evaluators do the same, although
     * MongoDB does not guarantee it and its planner may reorder branches.
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileOr(mixed $query): \Closure
    {
        if (!is_array($query) || !array_is_list($query)) {
            throw new SyntaxError('$or can only operate on arrays of queries');
        }

        if ($query === []) {
            throw new SyntaxError('$or requires a non-empty array');
        }

        $children = [];
        foreach ($query as $i => $item) {
            if ($this->track) {
                $this->path[] = $i;
            }
            $children[] = $this->compileQueryNode($item);
            if ($this->track) {
                array_pop($this->path);
            }
        }

        if (count($children) === 1) {
            return $children[0];
        }

        return static function (array $data) use ($children): bool {
            foreach ($children as $child) {
                if ($child($data)) {
                    return true;
                }
            }
            return false;
        };
    }

    /**
     * Evaluates the $and logical operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/and/
     *
     * Syntax: { $and: [ <expression1>, <expression2>, ... ] }
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileAnd(mixed $query): \Closure
    {
        if (!is_array($query) || !array_is_list($query)) {
            throw new SyntaxError('$and can only operate on arrays of queries');
        }

        if ($query === []) {
            throw new SyntaxError('$and requires a non-empty array');
        }

        $children = [];
        foreach ($query as $i => $item) {
            if ($this->track) {
                $this->path[] = $i;
            }
            $children[] = $this->compileQueryNode($item);
            if ($this->track) {
                array_pop($this->path);
            }
        }

        return self::all($children);
    }

    /**
     * Short-circuiting AND over query nodes.
     *
     * @param list<\Closure(array<mixed>): bool> $children
     * @return \Closure(array<mixed>): bool
     */
    private static function all(array $children): \Closure
    {
        if (count($children) === 1) {
            return $children[0];
        }

        return static function (array $data) use ($children): bool {
            foreach ($children as $child) {
                if (!$child($data)) {
                    return false;
                }
            }
            return true;
        };
    }

    /**
     * A field condition: reads the field from the document, then runs the
     * field operators on it.
     *
     * Shortcut styles:
     *   {age: 37}               is {age: {$eq: 37}}
     *   {age: null}             is {age: {$eq: null}}
     *   {age: {$gt: 30, $lt: 40}} runs both operators
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileField(string $variableName, mixed $expression): \Closure
    {
        $segments = self::parseFieldPath($variableName);

        if (count($segments) === 1 && $segments[0][0] === false) {
            // a plain top-level field like "age"
            $key = $segments[0][1];

            $this->checkFieldExpression($expression);

            // the common shapes become a single closure that reads the field
            // and compares it
            $fused = $this->compileFusedField($variableName, $key, $expression);
            if ($fused !== null) {
                return $fused;
            }

            $tests = $this->compileFieldTests($variableName, $expression);
            if (count($tests) === 1) {
                $test = $tests[0];
                return function (array $data) use ($key, $variableName, $test): bool {
                    return $test($data[$key] ?? $this->nullOrUnknown($data, $key, $variableName), $data);
                };
            }

            // Every operator runs; the results are ANDed.
            return function (array $data) use ($key, $variableName, $tests): bool {
                $value = $data[$key] ?? $this->nullOrUnknown($data, $key, $variableName);
                $result = true;
                foreach ($tests as $test) {
                    $result = $test($value, $data) && $result;
                }
                return $result;
            };
        }

        $test = $this->compileFieldExpression($variableName, $expression);
        $resolve = $this->compilePath($variableName, $segments);
        return static fn(array $data): bool => $test($resolve($data), $data);
    }

    /**
     * One closure for {field: literal}, {field: {$eq: x}} and
     * {field: {$gt|$gte|$lt|$lte: x}} on a top-level field, instead of a
     * field closure calling an operator closure. Returns null for every other
     * shape, which then takes the general path.
     *
     * Not used with an operand evaluator (the operand is only known per
     * document) or for ordering in strict mode. The caller has checked the
     * expression with checkFieldExpression().
     *
     * @return (\Closure(array<mixed>): bool)|null
     */
    private function compileFusedField(string $variableName, int|string $key, mixed $expression): ?\Closure
    {
        if ($this->operandEvaluator !== null) {
            return null;
        }

        if (!is_array($expression) || array_is_list($expression)) {
            // {field: literal} is {field: {$eq: literal}}
            return $this->compileFusedEq($variableName, $key, $expression);
        }

        $operator = array_key_first($expression);

        if (!isset(self::QUERY_OPERATORS[$operator])) {
            // an object that is not an {$operator: operand} object is compared with $eq
            return $this->compileFusedEq($variableName, $key, $expression);
        }

        if (count($expression) !== 1) {
            return null;
        }

        $operand = $expression[$operator];

        return match ($operator) {
            '$eq' => $this->compileFusedEq($variableName, $key, $operand),
            '$gt', '$gte', '$lt', '$lte' => $this->compileFusedOrdered($operator, $variableName, $key, $operand),
            default => null,
        };
    }

    /**
     * Fused $eq, see compileEq().
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileFusedEq(string $variableName, int|string $key, mixed $operand): \Closure
    {
        return fn(array $d): bool => ($v = $d[$key] ?? $this->nullOrUnknown($d, $key, $variableName)) === $operand
            || $this->evalEq($variableName, $v, $operand);
    }

    /**
     * Fused $gt / $gte / $lt / $lte, see compileOrdered() for the rules.
     *
     * @return (\Closure(array<mixed>): bool)|null
     */
    private function compileFusedOrdered(string $operator, string $name, int|string $k, mixed $x): ?\Closure
    {
        if ($this->strictMode || (is_float($x) && is_nan($x)) || ((is_int($x) || is_float($x)) && abs($x) >= 9007199254740992)) {
            // strict mode, NaN and numbers beyond 2^53: general path
            return null;
        }

        if (is_int($x) || is_float($x)) {
            // number: matches numbers and numeric strings, compared as numbers
            return match ($operator) {
                '$gt' => fn(array $d): bool => (is_int($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) || is_float($v) || (is_string($v) && is_numeric($v))) && $v > $x,
                '$gte' => fn(array $d): bool => (is_int($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) || is_float($v) || (is_string($v) && is_numeric($v))) && $v >= $x,
                '$lt' => fn(array $d): bool => (is_int($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) || is_float($v) || (is_string($v) && is_numeric($v))) && $v < $x,
                default => fn(array $d): bool => (is_int($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) || is_float($v) || (is_string($v) && is_numeric($v))) && $v <= $x,
            };
        }

        if (is_string($x) && is_numeric($x)) {
            // numeric string: strings compare as text, numbers as numbers
            return match ($operator) {
                '$gt' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) ? strcmp($v, $x) > 0 : (is_int($v) || is_float($v)) && $v > $x,
                '$gte' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) ? strcmp($v, $x) >= 0 : (is_int($v) || is_float($v)) && $v >= $x,
                '$lt' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) ? strcmp($v, $x) < 0 : (is_int($v) || is_float($v)) && $v < $x,
                default => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) ? strcmp($v, $x) <= 0 : (is_int($v) || is_float($v)) && $v <= $x,
            };
        }

        if (is_string($x)) {
            // other string: matches strings only, compared as text
            return match ($operator) {
                '$gt' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) && strcmp($v, $x) > 0,
                '$gte' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) && strcmp($v, $x) >= 0,
                '$lt' => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) && strcmp($v, $x) < 0,
                default => fn(array $d): bool => is_string($v = $d[$k] ?? $this->nullOrUnknown($d, $k, $name)) && strcmp($v, $x) <= 0,
            };
        }

        // null, bool, array, object operand: general path
        return null;
    }

    /**
     * Value of a top-level field whose lookup returned null: null if the key
     * exists (the value is null), otherwise the unknown-variable handling.
     *
     * @param array<mixed> $data
     */
    private function nullOrUnknown(array $data, int|string $key, string $variableName): mixed
    {
        return array_key_exists($key, $data) ? null : $this->unknownVariable($variableName);
    }

    /**
     * Evaluates the $expr operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/expr/
     *
     * Syntax: { $expr: { <expression> } }
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileExpr(mixed $expression): \Closure
    {
        if (!is_array($expression) || array_is_list($expression)) {
            throw new SyntaxError('$expr requires an object expression');
        }

        $first = array_key_first($expression);
        if ($this->operandEvaluator === null && (!is_string($first) || !str_starts_with($first, '$'))) {
            // a plain object would be a non-empty array, which counts as true,
            // so a typo like {gte: [...]} would match every document
            throw new SyntaxError("\$expr requires an operator expression like {\$gt: [\"\$a\", 1]}, got key: {$first}");
        }

        $node = $this->compileAgg($expression);

        return static fn(array $data): bool => (bool)$node($data);
    }

    // ------------------------------------------------------------------
    // Field paths
    // ------------------------------------------------------------------

    /**
     * parsePath() for paths in a query: also rejects paths with an empty
     * segment, in the same pass: "", ".", "a..b", ".a", "a." (an escaped dot
     * as in "a\." is part of the key). Without this, "." would resolve to the
     * whole document and "a..b" would silently mean "a.b".
     *
     * @return list<array{bool, int|string}>
     */
    private static function parseFieldPath(string $path): array
    {
        if ($path !== '' && strpbrk($path, '.[\\') === false) {
            return [[false, $path]]; // a plain field name like "age"
        }

        if (!str_contains($path, '\\')) {
            // no escaped dots: split once; an empty piece is the error
            $parts = explode('.', $path);
            foreach ($parts as $part) {
                if ($part === '') {
                    throw new SyntaxError("Invalid field path \"{$path}\": empty segment");
                }
            }
            if (!str_contains($path, '[')) {
                $segments = [];
                foreach ($parts as $part) {
                    $segments[] = [false, $part];
                }
                return $segments;
            }
            return self::parsePath($path); // array indexes like "items[0].q"
        }

        // escaped dots: walk the characters ("[0]" starts a segment of its own)
        $segmentLength = 0;
        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];
            if ($char === '\\') {
                $segmentLength += 2;
                $i++;
            } elseif ($char === '.') {
                if ($segmentLength === 0) {
                    throw new SyntaxError("Invalid field path \"{$path}\": empty segment");
                }
                $segmentLength = 0;
            } elseif ($char === '[') {
                $segmentLength = 1; // "[0]" starts a new, non-empty segment
            } else {
                $segmentLength++;
            }
        }

        if ($segmentLength === 0) {
            throw new SyntaxError("Invalid field path \"{$path}\": empty segment");
        }

        return self::parsePath($path);
    }

    /**
     * @param list<array{bool, int|string}>|null $segments
     * @return \Closure(array<mixed>): mixed
     */
    private function compilePath(string $variableName, ?array $segments = null): \Closure
    {
        $segments ??= self::parsePath($variableName);

        if (count($segments) === 1 && $segments[0][0] === false) {
            $key = $segments[0][1];
            return fn(array $data): mixed => $data[$key] ?? $this->nullOrUnknown($data, $key, $variableName);
        }

        return function (array $data) use ($segments, $variableName): mixed {
            $value = $data;
            foreach ($segments as [$isIndex, $key]) {
                if ($isIndex) {
                    if (!is_array($value) || !isset($value[$key])) {
                        // Variable doesn't exist (undefined in JS terms)
                        return $this->unknownVariable($variableName);
                    }
                } elseif (!is_array($value) || !array_key_exists($key, $value)) {
                    // Variable doesn't exist (undefined in JS terms)
                    return $this->unknownVariable($variableName);
                }

                $value = $value[$key];

                if ($value === null) {
                    break;
                }
            }
            return $value;
        };
    }

    private function unknownVariable(string $variableName): mixed
    {
        if (!$this->throwOnUnknownVariable) {
            return null;
        }

        throw new UnknownVariableException("Variable '{$variableName}' is not defined");
    }

    // ------------------------------------------------------------------
    // Field operators: Closure(mixed $value, array $data): bool
    // ------------------------------------------------------------------

    /**
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileFieldExpression(string $variableName, mixed $expression): \Closure
    {
        $this->checkFieldExpression($expression);
        $tests = $this->compileFieldTests($variableName, $expression);

        if (count($tests) === 1) {
            return $tests[0];
        }

        // Every operator runs; the results are ANDed.
        return static function (mixed $value, array $data) use ($tests): bool {
            $result = true;
            foreach ($tests as $test) {
                $result = $test($value, $data) && $result;
            }
            return $result;
        };
    }

    /**
     * One closure per operator of a field condition that was checked with
     * checkFieldExpression().
     *
     * @return non-empty-list<\Closure(mixed, array<mixed>): bool>
     */
    private function compileFieldTests(string $variableName, mixed $expression): array
    {
        if (!is_array($expression) || array_is_list($expression)) {
            // array (list), null, number, string, bool: wrap it
            $expression = ['$eq' => $expression];
        } elseif (!isset(self::QUERY_OPERATORS[array_key_first($expression)])) {
            // an object that is not an {$operator: operand} object
            $expression = ['$eq' => $expression];
        }

        $tests = [];
        foreach ($expression as $operator => $operand) {
            if ($this->track) {
                $this->path[] = (string)$operator;
            }
            $tests[] = $this->compileOperator($variableName, (string)$operator, $operand, $expression);
            if ($this->track) {
                array_pop($this->path);
            }
        }

        return $tests;
    }

    /**
     * Checks the shape of a field condition that only the query decides:
     *
     * - $options needs $regex
     * - without an operand evaluator, an object whose first key starts with
     *   "$" must be a known operator ({age: {$gtt: 18}} would otherwise be
     *   compared as a literal object and silently never match), and an object
     *   that starts with a field may not contain operators
     *
     * With an operand evaluator, such objects may be custom functions like
     * {$var: "x"}, which the evaluator resolves.
     */
    private function checkFieldExpression(mixed $expression): void
    {
        if (!is_array($expression) || array_is_list($expression)) {
            return;
        }

        $first = array_key_first($expression);

        if (isset(self::QUERY_OPERATORS[$first])) {
            if ((count($expression) > 1 || $first === '$options') && array_key_exists('$options', $expression) && !array_key_exists('$regex', $expression)) {
                throw new SyntaxError('$options requires $regex');
            }
            return; // other keys are checked by compileOperator()
        }

        if ($this->operandEvaluator !== null) {
            return;
        }

        if (is_string($first) && str_starts_with($first, '$')) {
            throw new SyntaxError("Unsupported query operator: {$first}");
        }

        foreach (array_keys($expression) as $key) {
            if (is_string($key) && str_starts_with($key, '$')) {
                throw new SyntaxError("Cannot mix fields and operators in one object: {$key}");
            }
        }
    }

    /**
     * Checks a literal operand. Without an operand evaluator this runs while
     * compiling; with one, when the evaluator returns the operand unchanged.
     *
     * @return true
     */
    private function validateOperand(string $operator, mixed $operand): bool
    {
        switch ($operator) {
            case '$in':
                if (!is_array($operand) || !array_is_list($operand)) {
                    throw new SyntaxError('$in requires an array, got ' . gettype($operand));
                }
                break;
            case '$gt':
            case '$gte':
            case '$lt':
            case '$lte':
                if (is_array($operand)) {
                    // arrays and objects never match ordering
                    throw new SyntaxError("{$operator} cannot compare with an array or object");
                }
                break;
            case '$not':
                if (!is_array($operand) || array_is_list($operand) || !isset(self::QUERY_OPERATORS[array_key_first($operand)])) {
                    throw new SyntaxError('$not requires an operator expression like {$not: {$gt: 5}}');
                }
                break;
        }
        return true;
    }

    /**
     * @param array<mixed> $expression the full {$operator: operand, ...} object
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileOperator(string $variableName, string $operator, mixed $operand, array $expression): \Closure
    {
        if ($this->operandEvaluator !== null) {
            return $this->compileDynamicOperator($variableName, $operator, $operand, $expression);
        }

        if ($operator === '$in' || $operator === '$not' || is_array($operand)) {
            // the only cases validateOperand() can reject
            $this->validateOperand($operator, $operand);
        }

        // The operand is a literal, so the operator can be specialized on it.
        return match ($operator) {
            '$eq' => $this->compileEq($variableName, $operand),
            '$ne' => fn(mixed $value, array $data): bool => $this->evalNe($variableName, $value, $operand),
            '$gt', '$gte', '$lt', '$lte' => $this->compileOrdered($operator, $variableName, $operand),
            '$in' => fn(mixed $value, array $data): bool => $this->evalIn($variableName, $value, $operand),
            '$regex' => $this->compileRegex($variableName, $operand, $expression),
            '$options' => static fn(mixed $value, array $data): bool => true,
            '$not' => $this->compileNot($variableName, $operand),
            default => throw new SyntaxError("Unsupported query operator: {$operator}"),
        };
    }

    /**
     * With an operand evaluator, the operand is only known per document: the
     * evaluator runs on it every time, as before.
     *
     * @param array<mixed> $expression
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileDynamicOperator(string $variableName, string $operator, mixed $operand, array $expression): \Closure
    {
        /** @var \Closure(mixed, array<mixed>): mixed $evaluator */
        $evaluator = $this->operandEvaluator;

        if ($operator === '$not') {
            /** @var (\Closure(mixed, array<mixed>): bool)|null $inner */
            $inner = null;
            $at = $this->path;   // for errors when the operand is compiled later
            return function (mixed $value, array $data) use ($evaluator, $variableName, $operand, &$inner, $at): bool {
                $resolved = $evaluator($operand, $data);
                if ($resolved === $operand) {
                    $inner ??= $this->compileAt($at, function () use ($variableName, $operand): \Closure {
                        $this->validateOperand('$not', $operand);
                        return $this->compileFieldExpression($variableName, $operand);
                    });
                    return !$inner($value, []);
                }
                return !$this->compileAt($at, fn(): \Closure => $this->compileFieldExpression($variableName, $resolved))($value, []);
            };
        }

        $at = $this->path; // for errors found at runtime
        $options = $expression['$options'] ?? null;

        $impl = match ($operator) {
            '$eq' => fn(mixed $value, mixed $resolved): bool => $this->evalEq($variableName, $value, $resolved),
            '$ne' => fn(mixed $value, mixed $resolved): bool => $this->evalNe($variableName, $value, $resolved),
            '$gt', '$gte', '$lt', '$lte' => fn(mixed $value, mixed $resolved): bool => $this->evalOrdered($operator, $value, $resolved, $variableName),
            '$in' => fn(mixed $value, mixed $resolved): bool => $this->evalIn($variableName, $value, $resolved),
            '$regex' => fn(mixed $value, mixed $resolved): bool => $this->matchRegex(
                $this->compileAt($at, fn(): string => self::buildRegex($resolved, $options)),
                $value,
                $variableName,
            ),
            '$options' => static fn(mixed $value, mixed $resolved): bool => true,
            default => throw new SyntaxError("Unsupported query operator: {$operator}"),
        };

        // A literal operand is checked like without an evaluator, once the
        // evaluator returns it unchanged.
        $checked = false;
        return function (mixed $value, array $data) use ($impl, $evaluator, $operator, $operand, $at, &$checked): bool {
            $resolved = $evaluator($operand, $data);
            if (!$checked && $resolved === $operand) {
                $this->compileAt($at, fn(): bool => $this->validateOperand($operator, $operand));
                $checked = true;
            }
            return $impl($value, $resolved);
        };
    }

    /**
     * Evaluates the $eq query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/eq/
     *
     * Syntax: { <field>: { $eq: <value> } } or shorthand: { <field>: <value> }
     *
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileEq(string $variableName, mixed $operand): \Closure
    {
        // An identical value is always equal, so that check runs first.
        return fn(mixed $value, array $data): bool => $value === $operand || $this->evalEq($variableName, $value, $operand);
    }

    /**
     * Evaluates the $gt / $gte / $lt / $lte query operators
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/gt/
     *
     * Syntax: { <field>: { $gt: <value> } }
     *
     * Only values that can be ordered against each other are compared (see
     * isOrderable()); anything else does not match, or throws in strict mode.
     * The type check for the literal operand is done here, once, so each call
     * only checks the document value before using PHP's native operator.
     *
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileOrdered(string $operator, string $variableName, mixed $operand): \Closure
    {
        if ($this->strictMode) {
            return fn(mixed $v, array $d): bool => $this->evalOrdered($operator, $v, $operand, $variableName);
        }

        if (is_float($operand) && is_nan($operand)) {
            // NaN: only $gte / $lte match, and only NaN (NaN equals NaN)
            return match ($operator) {
                '$gte', '$lte' => static fn(mixed $v, array $d): bool => is_float($v) && is_nan($v),
                default => static fn(mixed $v, array $d): bool => false,
            };
        }

        if ((is_int($operand) || is_float($operand)) && abs($operand) >= 9007199254740992) {
            // 2^53 and beyond: integers vs floats need the exact comparison
            return fn(mixed $v, array $d): bool => $this->evalOrdered($operator, $v, $operand, $variableName);
        }

        if (is_int($operand) || is_float($operand)) {
            // number: matches numbers and numeric strings, compared as numbers
            return match ($operator) {
                '$gt' => static fn(mixed $v, array $d): bool => (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && $v > $operand,
                '$gte' => static fn(mixed $v, array $d): bool => (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && $v >= $operand,
                '$lt' => static fn(mixed $v, array $d): bool => (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && $v < $operand,
                default => static fn(mixed $v, array $d): bool => (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && $v <= $operand,
            };
        }

        if (is_string($operand) && is_numeric($operand)) {
            // numeric string: strings compare as text, numbers as numbers
            return match ($operator) {
                '$gt' => static fn(mixed $v, array $d): bool => is_string($v) ? strcmp($v, $operand) > 0 : (is_int($v) || is_float($v)) && $v > $operand,
                '$gte' => static fn(mixed $v, array $d): bool => is_string($v) ? strcmp($v, $operand) >= 0 : (is_int($v) || is_float($v)) && $v >= $operand,
                '$lt' => static fn(mixed $v, array $d): bool => is_string($v) ? strcmp($v, $operand) < 0 : (is_int($v) || is_float($v)) && $v < $operand,
                default => static fn(mixed $v, array $d): bool => is_string($v) ? strcmp($v, $operand) <= 0 : (is_int($v) || is_float($v)) && $v <= $operand,
            };
        }

        if (is_string($operand)) {
            // other string: matches strings only, compared as text
            return match ($operator) {
                '$gt' => static fn(mixed $v, array $d): bool => is_string($v) && strcmp($v, $operand) > 0,
                '$gte' => static fn(mixed $v, array $d): bool => is_string($v) && strcmp($v, $operand) >= 0,
                '$lt' => static fn(mixed $v, array $d): bool => is_string($v) && strcmp($v, $operand) < 0,
                default => static fn(mixed $v, array $d): bool => is_string($v) && strcmp($v, $operand) <= 0,
            };
        }

        if ($operand === null || is_bool($operand)) {
            // null orders only against null (equal), booleans only against
            // booleans (false < true)
            return fn(mixed $v, array $d): bool => $this->evalOrdered($operator, $v, $operand, $variableName);
        }

        // array, object: nothing can be ordered against it
        return static fn(mixed $v, array $d): bool => false;
    }

    /**
     * Evaluates the $regex query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/regex/
     *
     * Syntax: { <field>: { $regex: <pattern>, $options: <options> } }
     *
     * The pattern is built and checked once, here; see buildRegex() and
     * matchRegex().
     *
     * @param array<mixed> $expression the full expression (may contain $options)
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileRegex(string $variableName, mixed $operand, array $expression): \Closure
    {
        $regex = self::buildRegex($operand, $expression['$options'] ?? null);

        return fn(mixed $value, array $data): bool => $this->matchRegex($regex, $value, $variableName);
    }

    /**
     * Builds a PCRE pattern from a $regex pattern and $options and checks that
     * it compiles. Throws a SyntaxError for:
     *
     * - a pattern that is not a string
     * - $options that is not a string or contains other letters than
     *   i, m, s, x, u (the options MongoDB supports)
     * - a pattern that ends with an unescaped backslash
     * - a pattern that PCRE cannot compile
     *
     * A "/" in the pattern is escaped unless it already is ("a\/b" stays).
     */
    private static function buildRegex(mixed $pattern, mixed $options): string
    {
        if (!is_string($pattern)) {
            throw new SyntaxError('$regex requires a string pattern, got ' . gettype($pattern));
        }

        $options ??= '';
        if (!is_string($options)) {
            throw new SyntaxError('$options requires a string, got ' . gettype($options));
        }
        if (strspn($options, 'imsxu') !== strlen($options)) {
            $invalid = $options[strspn($options, 'imsxu')];
            throw new SyntaxError("Unsupported \$regex option: {$invalid} (supported: i, m, s, x, u)");
        }

        // escape unescaped delimiters; copy escape sequences as they are
        $body = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                if ($i + 1 === $length) {
                    throw new SyntaxError('$regex pattern ends with an unescaped backslash');
                }
                $body .= $char . $pattern[++$i];
            } elseif ($char === '/') {
                $body .= '\\/';
            } else {
                $body .= $char;
            }
        }

        $regex = '/' . $body . '/' . $options;

        $warning = null;
        set_error_handler(static function (int $errno, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            $compiled = preg_match($regex, '');
        } finally {
            restore_error_handler();
        }

        if ($compiled === false) {
            $reason = $warning !== null ? preg_replace('/^preg_match\(\): /', '', $warning) : preg_last_error_msg();
            throw new SyntaxError("Invalid \$regex pattern \"{$pattern}\": {$reason}");
        }

        return $regex;
    }

    /**
     * Matches a value against a pattern from buildRegex().
     *
     * Strings are matched as they are. Without strict mode, numbers are
     * matched as text ("12"); null, booleans, arrays and objects do not
     * match. In strict mode a non-string value other than null throws a
     * StrictTypeError.
     *
     * If matching fails (PCRE's backtrack limit, a value that is not valid
     * UTF-8 with the "u" option), a RegexError is thrown instead of treating
     * it as "no match", which $not would turn into a match.
     */
    private function matchRegex(string $regex, mixed $value, string $variableName): bool
    {
        if (is_string($value)) {
            $subject = $value;
        } elseif ($value === null) {
            return false;
        } elseif ($this->strictMode) {
            throw new StrictTypeError("\$regex: variable {$variableName} is of type " . gettype($value) . ' while a string is expected');
        } elseif (is_int($value) || (is_float($value) && !is_nan($value))) {
            $subject = (string)$value;
        } else {
            return false;
        }

        $result = preg_match($regex, $subject);
        if ($result === false) {
            throw new RegexError("\$regex: matching variable {$variableName} failed: " . preg_last_error_msg());
        }

        return $result === 1;
    }

    /**
     * Evaluates the $not query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/not/
     *
     * Syntax: { <field>: { $not: { <operator-expression> } } }
     *
     * @return \Closure(mixed, array<mixed>): bool
     */
    private function compileNot(string $variableName, mixed $operand): \Closure
    {
        $inner = $this->compileFieldExpression($variableName, $operand);

        // The negated expression gets empty data, as before.
        return static fn(mixed $value, array $data): bool => !$inner($value, []);
    }

    // ------------------------------------------------------------------
    // Field operator implementations (also used with an operand evaluator)
    // ------------------------------------------------------------------

    /**
     * Integers and floats are one numeric type (12 equals 12.0), in both
     * modes. NaN equals NaN, as in MongoDB.
     */
    private static function numbersEqual(int|float $a, int|float $b): bool
    {
        if (self::needsExactComparison($a, $b)) {
            return self::compareNumbers($a, $b) === 0;
        }

        return $a == $b || (is_float($a) && is_float($b) && is_nan($a) && is_nan($b));
    }

    /**
     * PHP compares an integer with a float by converting the integer to a
     * float, which loses precision from 2^53 on: 2^53 + 1 would equal 2^53.0.
     * That only matters when both values are at least 2^53 in magnitude.
     */
    private static function needsExactComparison(int|float $a, int|float $b): bool
    {
        return is_int($a) !== is_int($b) && abs($a) >= 9007199254740992 && abs($b) >= 9007199254740992;
    }

    /**
     * Exact comparison of two numbers (not NaN): -1, 0 or 1.
     */
    private static function compareNumbers(int|float $a, int|float $b): int
    {
        if (is_int($a) === is_int($b)) {
            return $a <=> $b;
        }

        return is_int($a) ? self::compareIntFloat($a, (float)$b) : -self::compareIntFloat((int)$b, $a);
    }

    private static function compareIntFloat(int $i, float $f): int
    {
        if ($f >= 9.2233720368547758E18) {
            // 2^63 and above (including INF) is beyond every integer
            return -1;
        }
        if ($f < -9.2233720368547758E18) {
            return 1;
        }

        // the integer part of a float in this range converts exactly
        $whole = (int)$f;
        if ($i !== $whole) {
            return $i <=> $whole;
        }

        $fraction = $f - (float)$whole;
        return $fraction > 0 ? -1 : ($fraction < 0 ? 1 : 0);
    }

    /**
     * Non-strict equality between a string and a number: "137" equals 137,
     * compared as text. Null if $a and $b are not a string/number pair.
     */
    private static function stringNumberEqual(mixed $a, mixed $b): ?bool
    {
        if (is_string($a) && (is_int($b) || is_float($b))) {
            return !is_nan((float)$b) && $a === (string)$b;
        }
        if (is_string($b) && (is_int($a) || is_float($a))) {
            return !is_nan((float)$a) && (string)$a === $b;
        }
        return null;
    }

    /**
     * @param string $operator for the exception message ($eq, or $ne when
     *                         called from evalNe() in strict mode)
     */
    private function evalEq(string $variableName, mixed $variableValue, mixed $operand, string $operator = '$eq'): bool
    {
        if ((is_int($variableValue) || is_float($variableValue)) && (is_int($operand) || is_float($operand))) {
            return self::numbersEqual($variableValue, $operand);
        }

        if (gettype($variableValue) === gettype($operand) && !is_array($variableValue) && !is_object($variableValue)) {
            // boolean, number, string
            return $variableValue === $operand;
        }

        if (is_array($variableValue) && array_is_list($variableValue)) {
            // an array matches if it equals the operand or contains it
            // (so {v: null} matches [null])
            return $this->arrayEqualsOrContains($variableValue, $operand);
        }

        if ($variableValue === null || $operand === null) {
            // either variableValue or operand are null or both are null
            return $variableValue === $operand;
        }

        if (is_array($variableValue) && is_array($operand)) {
            return $this->isEqualObject($variableValue, $operand);
        }

        if ($this->strictMode) {
            throw new StrictTypeError("{$operator}: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }

        return self::stringNumberEqual($variableValue, $operand) ?? false;
    }

    /**
     * Evaluates the $ne query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/ne/
     *
     * Syntax: { <field>: { $ne: <value> } }
     *
     * The negation of $eq: an array field does not match if it equals the
     * operand or contains it, so {role: {$ne: "banned"}} does not match
     * ["banned", "admin"]. In strict mode $ne throws exactly when $eq would
     * (two non-null values of different types, e.g. "137" vs 137).
     */
    private function evalNe(string $variableName, mixed $variableValue, mixed $operand): bool
    {
        if ($this->strictMode) {
            return !$this->evalEq($variableName, $variableValue, $operand, '$ne');
        }

        if ((is_int($variableValue) || is_float($variableValue)) && (is_int($operand) || is_float($operand))) {
            return !self::numbersEqual($variableValue, $operand);
        }

        if (is_array($variableValue) && array_is_list($variableValue)) {
            return !$this->arrayEqualsOrContains($variableValue, $operand);
        }

        $equal = self::stringNumberEqual($variableValue, $operand);
        if ($equal !== null) {
            return !$equal;
        }

        if (gettype($variableValue) !== gettype($operand)) {
            return true;
        }

        return !$this->isEqual($variableValue, $operand);
    }

    /**
     * Evaluates the $in query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/in/
     *
     * Syntax: { <field>: { $in: [ <value1>, <value2>, ... ] } }
     *
     * Matches if the field equals one of the values, or, for an array field,
     * if the array equals one of the values or contains one of them, so
     * {role: {$in: ["admin"]}} matches ["user", "admin"].
     */
    private function evalIn(string $variableName, mixed $variableValue, mixed $operand): bool
    {
        if (!is_array($operand) || !array_is_list($operand)) {
            if ($this->strictMode) {
                throw new StrictTypeError("\$in: variable {$variableName}: operand must be of type array but is of type " . gettype($operand));
            }
            return false;
        }

        $isList = is_array($variableValue) && array_is_list($variableValue);

        foreach ($operand as $item) {
            if ($isList ? $this->arrayEqualsOrContains($variableValue, $item) : $this->isEqual($variableValue, $item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * $eq for an array field: the array equals $operand, or one of its
     * elements does.
     *
     * @param list<mixed> $array
     */
    private function arrayEqualsOrContains(array $array, mixed $operand): bool
    {
        if (is_array($operand) && array_is_list($operand) && $this->isEqual($array, $operand)) {
            return true;
        }

        foreach ($array as $element) {
            if ($this->isEqual($element, $operand)) {
                return true;
            }
        }
        return false;
    }

    /**
     * $gt / $gte / $lt / $lte for values that are only known at runtime
     * (operand evaluator, $expr).
     *
     * @param string|null $variableName field name, or null inside $expr
     */
    private function evalOrdered(string $operator, mixed $a, mixed $b, ?string $variableName): bool
    {
        if (!$this->isOrderable($operator, $a, $b, $variableName)) {
            return false;
        }

        if (is_string($a) && is_string($b)) {
            // two strings compare as text, like MongoDB ("10" < "9")
            $cmp = strcmp($a, $b);
            return match ($operator) {
                '$gt' => $cmp > 0,
                '$gte' => $cmp >= 0,
                '$lt' => $cmp < 0,
                default => $cmp <= 0,
            };
        }

        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b)) && self::needsExactComparison($a, $b)) {
            $cmp = self::compareNumbers($a, $b);
            return match ($operator) {
                '$gt' => $cmp > 0,
                '$gte' => $cmp >= 0,
                '$lt' => $cmp < 0,
                default => $cmp <= 0,
            };
        }

        $result = match ($operator) {
            '$gt' => $a > $b,
            '$gte' => $a >= $b,
            '$lt' => $a < $b,
            default => $a <= $b,
        };

        // NaN equals NaN, so NaN >= NaN and NaN <= NaN
        if (!$result && ($operator === '$gte' || $operator === '$lte') && is_float($a) && is_float($b) && is_nan($a) && is_nan($b)) {
            return true;
        }

        return $result;
    }

    /**
     * Whether $a and $b can be ordered with PHP's native comparison operators
     * without type juggling letting a value through:
     *
     * - number vs number (integers and floats are one numeric type)
     * - string vs string
     * - boolean vs boolean (false < true)
     * - null vs null (equal)
     * - number vs numeric string (not in strict mode)
     *
     * Everything else (null vs number, bool vs number, arrays, objects,
     * "admin" vs 18) cannot be ordered: it does not match, or throws a
     * StrictTypeError in strict mode.
     *
     * @param string|null $variableName field name, or null inside $expr
     */
    private function isOrderable(string $operator, mixed $a, mixed $b, ?string $variableName): bool
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return true;
        }

        if (($a === null && $b === null) || (is_bool($a) && is_bool($b))) {
            return true;
        }

        if ($a === null || $b === null) {
            // null is "no value", not a wrong type: no match, also in strict mode
            return false;
        }

        if ($this->strictMode) {
            $subject = $variableName !== null ? "variable {$variableName}" : 'first operand';
            $other = $variableName !== null ? 'operand' : 'second operand';

            if (gettype($a) !== gettype($b)) {
                throw new StrictTypeError("{$operator}: {$subject} is of type " . gettype($a) . " while {$other} is of type " . gettype($b));
            }
            if (!is_string($a)) {
                throw new StrictTypeError("{$operator}: {$subject} is of type " . gettype($a) . ', which cannot be ordered');
            }
            return true;
        }

        if (is_int($a) || is_float($a)) {
            // number vs numeric string
            return is_string($b) && is_numeric($b);
        }

        if (is_string($a)) {
            return is_string($b) || ((is_int($b) || is_float($b)) && is_numeric($a));
        }

        return false;
    }

    private function isEqual(mixed $a, mixed $b): bool
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return self::numbersEqual($a, $b);
        }

        if (gettype($a) === gettype($b) && !is_array($a) && !is_object($a)) {
            // boolean, number, string
            return $a === $b;
        }

        if ($a === null || $b === null) {
            // either a or b are null or both are null
            return $a === $b;
        }

        if (is_array($a) && array_is_list($a) && is_array($b) && array_is_list($b) && count($a) === count($b)) {
            for ($i = 0; $i < count($a); ++$i) {
                if (!$this->isEqual($a[$i], $b[$i])) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($a) && is_array($b)) {
            return $this->isEqualObject($a, $b);
        }

        // "137" equals 137 only in non-strict mode; strict mode never mixes
        // strings and numbers (no match, no exception, so mixed $in lists work)
        if ($this->strictMode) {
            return false;
        }

        return self::stringNumberEqual($a, $b) ?? false;
    }

    /**
     * @param array<mixed> $a
     * @param array<mixed> $b
     */
    private function isEqualObject(array $a, array $b): bool
    {
        $aKeys = array_keys($a);
        $bKeys = array_keys($b);

        if (count($aKeys) !== count($bKeys)) {
            return false;
        }

        foreach ($aKeys as $aKey) {
            if (!array_key_exists($aKey, $b)) {
                return false;
            }
            if (!$this->isEqual($a[$aKey], $b[$aKey])) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Aggregation nodes ($expr): Closure(array $data): mixed
    // ------------------------------------------------------------------

    /**
     * Compiles one node of an aggregation expression.
     *
     * With an operand evaluator, the evaluator runs on every node first, as
     * before. Only if it returns the node unchanged is the compiled node
     * used (compiled on first use, so a custom function like {$var: ...} is
     * not rejected as an unknown operator). Otherwise the returned value is
     * compiled and evaluated on the spot.
     *
     * @return \Closure(array<mixed>): mixed
     */
    private function compileAgg(mixed $expression): \Closure
    {
        if ($this->operandEvaluator === null) {
            return $this->compileAggNode($expression);
        }

        $evaluator = $this->operandEvaluator;
        /** @var (\Closure(array<mixed>): mixed)|null $compiled */
        $compiled = null;
        $at = $this->path;   // for errors when the node is compiled later

        return function (array $data) use ($evaluator, $expression, &$compiled, $at): mixed {
            $resolved = $evaluator($expression, $data);
            if ($resolved === $expression) {
                $compiled ??= $this->compileAt($at, fn(): \Closure => $this->compileAggNode($expression));
                return $compiled($data);
            }
            return $this->compileAt($at, fn(): \Closure => $this->compileAggNode($resolved))($data);
        };
    }

    /**
     * Compiles the elements of a list of expressions, recording each index
     * in the error location.
     *
     * @param list<mixed> $list
     * @return list<\Closure(array<mixed>): mixed>
     */
    private function compileAggItems(array $list): array
    {
        $items = [];
        foreach ($list as $i => $item) {
            if ($this->track) {
                $this->path[] = $i;
            }
            $items[] = $this->compileAgg($item);
            if ($this->track) {
                array_pop($this->path);
            }
        }
        return $items;
    }

    /**
     * Compiles one named part of an operator, e.g. 'if' of $cond, recording
     * it in the error location.
     *
     * @return \Closure(array<mixed>): mixed
     */
    private function compileAggPart(string $name, mixed $expression): \Closure
    {
        if ($this->track) {
            $this->path[] = $name;
        }
        $node = $this->compileAgg($expression);
        if ($this->track) {
            array_pop($this->path);
        }
        return $node;
    }

    /**
     * @return \Closure(array<mixed>): mixed
     */
    private function compileAggNode(mixed $expression): \Closure
    {
        // Field references (strings starting with $)
        if (is_string($expression) && str_starts_with($expression, '$')) {
            $path = substr($expression, 1);
            return $this->compilePath($path, self::parseFieldPath($path));
        }

        // Literal values
        if (!is_array($expression)) {
            return static fn(array $data): mixed => $expression;
        }

        // Arrays (lists)
        if (array_is_list($expression)) {
            $items = $this->compileAggItems($expression);
            return static function (array $data) use ($items): array {
                $result = [];
                foreach ($items as $item) {
                    $result[] = $item($data);
                }
                return $result;
            };
        }

        $key = array_key_first($expression);

        // An object that is not an operator is returned as-is
        if (!is_string($key) || !str_starts_with($key, '$')) {
            foreach (array_keys($expression) as $other) {
                if (is_string($other) && str_starts_with($other, '$')) {
                    throw new SyntaxError("Cannot mix fields and operators in one object: {$other}");
                }
            }
            return static fn(array $data): mixed => $expression;
        }

        // An operator object has exactly one key, as in MongoDB; otherwise
        // {$gte: [...], $lt: [...]} would silently ignore $lt
        if (count($expression) > 1) {
            throw new SyntaxError('An operator expression must have exactly one key, found: ' . implode(', ', array_keys($expression)));
        }

        $operands = $expression[$key];

        if ($this->track) {
            $this->path[] = $key;
        }

        $node = match ($key) {
            '$add' => $this->compileAggAdd($operands),
            '$subtract' => $this->compileAggSubtract($operands),
            '$multiply' => $this->compileAggMultiply($operands),
            '$divide' => $this->compileAggDivide($operands),
            '$mod' => $this->compileAggMod($operands),
            '$eq' => $this->compileAggEq($operands, false),
            '$ne' => $this->compileAggEq($operands, true),
            '$gt', '$gte', '$lt', '$lte' => $this->compileAggOrdered($key, $operands),
            '$not' => $this->compileAggNot($operands),
            '$in' => $this->compileAggIn($operands),
            '$cond' => $this->compileAggCond($operands),
            '$size' => $this->compileAggSize($operands),
            '$toLower' => $this->compileAggCase($operands, '$toLower', mb_strtolower(...)),
            '$toUpper' => $this->compileAggCase($operands, '$toUpper', mb_strtoupper(...)),
            '$substr' => $this->compileAggSubstr($operands, '$substr', mb_substr(...)),
            '$substrBytes' => $this->compileAggSubstr($operands, '$substrBytes', substr(...)),
            '$strLenBytes' => $this->compileAggStrLen($operands, '$strLenBytes', strlen(...)),
            '$strLenCP' => $this->compileAggStrLen($operands, '$strLenCP', mb_strlen(...)),
            '$trim' => $this->compileAggTrim($operands, '$trim', trim(...)),
            '$ltrim' => $this->compileAggTrim($operands, '$ltrim', ltrim(...)),
            '$rtrim' => $this->compileAggTrim($operands, '$rtrim', rtrim(...)),
            '$concat' => $this->compileAggConcat($operands),
            '$literal' => self::compileAggLiteral($operands),
            default => throw new SyntaxError("Unsupported aggregation operator: {$key}"),
        };

        if ($this->track) {
            array_pop($this->path);
        }
        return $node;
    }

    /**
     * $literal: https://www.mongodb.com/docs/manual/reference/operator/aggregation/literal/
     *
     * Syntax: { $literal: <value> }
     *
     * Returns the value without parsing it: "$price" stays the string
     * "$price" instead of a field reference, and {$add: [2, 3]} stays that
     * object. Nothing inside is compiled, evaluated or passed to the operand
     * evaluator.
     *
     * @return \Closure(array<mixed>): mixed
     */
    private static function compileAggLiteral(mixed $value): \Closure
    {
        return static fn(array $data): mixed => $value;
    }

    /**
     * Helper to compile exactly 2 operands for binary operators
     *
     * @return array{\Closure(array<mixed>): mixed, \Closure(array<mixed>): mixed}
     */
    private function compileBinary(mixed $operands, string $operator): array
    {
        if (!is_array($operands) || !array_is_list($operands) || count($operands) !== 2) {
            throw new SyntaxError("{$operator} requires an array of exactly 2 operands");
        }

        [$first, $second] = $this->compileAggItems($operands);
        return [$first, $second];
    }

    /**
     * A literal divisor of 0 is a syntax error, found while compiling
     * instead of with the first document that reaches it.
     */
    private static function assertNotLiteralZero(mixed $operands, string $operator): void
    {
        if (is_array($operands) && array_key_exists(1, $operands)
            && (is_int($operands[1]) || is_float($operands[1])) && $operands[1] == 0) {
            throw new SyntaxError("{$operator}: division by zero");
        }
    }

    /**
     * @return list<\Closure(array<mixed>): mixed>
     */
    private function compileList(mixed $operands, string $message): array
    {
        if (!is_array($operands) || !array_is_list($operands)) {
            throw new SyntaxError($message);
        }

        return $this->compileAggItems($operands);
    }

    /**
     * $add: https://www.mongodb.com/docs/manual/reference/operator/aggregation/add/
     *
     * Syntax: { $add: [ <expression1>, <expression2>, ... ] }
     *
     * @return \Closure(array<mixed>): (int|float)
     */
    private function compileAggAdd(mixed $operands): \Closure
    {
        $items = $this->compileList($operands, '$add requires an array of operands');

        return static function (array $data) use ($items): int|float {
            $sum = 0;
            foreach ($items as $item) {
                $value = $item($data);
                if (!is_numeric($value)) {
                    throw new SyntaxError('$add operands must be numeric');
                }
                $sum += $value;
            }
            return $sum;
        };
    }

    /**
     * $subtract: https://www.mongodb.com/docs/manual/reference/operator/aggregation/subtract/
     *
     * Syntax: { $subtract: [ <expression1>, <expression2> ] }
     *
     * @return \Closure(array<mixed>): (int|float)
     */
    private function compileAggSubtract(mixed $operands): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, '$subtract');

        return static function (array $data) use ($first, $second): int|float {
            $value1 = $first($data);
            $value2 = $second($data);
            if (!is_numeric($value1) || !is_numeric($value2)) {
                throw new SyntaxError('$subtract operands must be numeric');
            }
            return $value1 - $value2;
        };
    }

    /**
     * $multiply: https://www.mongodb.com/docs/manual/reference/operator/aggregation/multiply/
     *
     * Syntax: { $multiply: [ <expression1>, <expression2>, ... ] }
     *
     * @return \Closure(array<mixed>): (int|float)
     */
    private function compileAggMultiply(mixed $operands): \Closure
    {
        $items = $this->compileList($operands, '$multiply requires an array of operands');

        return static function (array $data) use ($items): int|float {
            $product = 1;
            foreach ($items as $item) {
                $value = $item($data);
                if (!is_numeric($value)) {
                    throw new SyntaxError('$multiply operands must be numeric');
                }
                $product *= $value;
            }
            return $product;
        };
    }

    /**
     * $divide: https://www.mongodb.com/docs/manual/reference/operator/aggregation/divide/
     *
     * Syntax: { $divide: [ <expression1>, <expression2> ] }
     *
     * @return \Closure(array<mixed>): (int|float)
     */
    private function compileAggDivide(mixed $operands): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, '$divide');
        self::assertNotLiteralZero($operands, '$divide');

        return static function (array $data) use ($first, $second): int|float {
            $value1 = $first($data);
            $value2 = $second($data);
            if (!is_numeric($value1) || !is_numeric($value2)) {
                throw new SyntaxError('$divide operands must be numeric');
            }
            if ($value2 == 0) {
                throw new SyntaxError('$divide: division by zero');
            }
            return $value1 / $value2;
        };
    }

    /**
     * $mod: https://www.mongodb.com/docs/manual/reference/operator/aggregation/mod/
     *
     * Syntax: { $mod: [ <expression1>, <expression2> ] }
     *
     * @return \Closure(array<mixed>): int
     */
    private function compileAggMod(mixed $operands): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, '$mod');
        self::assertNotLiteralZero($operands, '$mod');

        return static function (array $data) use ($first, $second): int {
            $value1 = $first($data);
            $value2 = $second($data);
            if (!is_numeric($value1) || !is_numeric($value2)) {
                throw new SyntaxError('$mod operands must be numeric');
            }
            if ($value2 == 0) {
                throw new SyntaxError('$mod: division by zero');
            }
            return $value1 % $value2;
        };
    }

    /**
     * $eq: https://www.mongodb.com/docs/manual/reference/operator/aggregation/eq/
     * $ne: https://www.mongodb.com/docs/manual/reference/operator/aggregation/ne/
     *
     * Syntax: { $eq: [ <expression1>, <expression2> ] }
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileAggEq(mixed $operands, bool $negate): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, $negate ? '$ne' : '$eq');

        return fn(array $data): bool => $this->isEqual($first($data), $second($data)) !== $negate;
    }

    /**
     * $gt:  https://www.mongodb.com/docs/manual/reference/operator/aggregation/gt/
     * $gte: https://www.mongodb.com/docs/manual/reference/operator/aggregation/gte/
     * $lt:  https://www.mongodb.com/docs/manual/reference/operator/aggregation/lt/
     * $lte: https://www.mongodb.com/docs/manual/reference/operator/aggregation/lte/
     *
     * Syntax: { $gt: [ <expression1>, <expression2> ] }
     *
     * Same ordering rules as the query operators, including strict mode.
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileAggOrdered(string $operator, mixed $operands): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, $operator);

        return fn(array $data): bool => $this->evalOrdered($operator, $first($data), $second($data), null);
    }

    /**
     * $not: https://www.mongodb.com/docs/manual/reference/operator/aggregation/not/
     *
     * Syntax: { $not: [ <expression> ] }
     *
     * false, null, 0 and 0.0 evaluate as false; all other values as true.
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileAggNot(mixed $operands): \Closure
    {
        if (!is_array($operands) || !array_is_list($operands) || count($operands) !== 1) {
            throw new SyntaxError('$not requires an array with exactly one expression');
        }

        $inner = $this->compileAggItems($operands)[0];

        return static function (array $data) use ($inner): bool {
            $value = $inner($data);
            return $value === false || $value === null || $value === 0 || $value === 0.0;
        };
    }

    /**
     * $in: https://www.mongodb.com/docs/manual/reference/operator/aggregation/in/
     *
     * Syntax: { $in: [ <expression>, <array expression> ] }
     *
     * @return \Closure(array<mixed>): bool
     */
    private function compileAggIn(mixed $operands): \Closure
    {
        [$first, $second] = $this->compileBinary($operands, '$in');

        return function (array $data) use ($first, $second): bool {
            $searchValue = $first($data);
            $arrayValue = $second($data);

            if (!is_array($arrayValue) || !array_is_list($arrayValue)) {
                throw new SyntaxError('$in: second operand must be an array');
            }

            foreach ($arrayValue as $item) {
                if ($this->isEqual($searchValue, $item)) {
                    return true;
                }
            }
            return false;
        };
    }

    /**
     * $cond: https://www.mongodb.com/docs/manual/reference/operator/aggregation/cond/
     *
     * Syntax: { $cond: { if: <boolean-expression>, then: <true-case>, else: <false-case> } }
     *
     * Only the chosen branch is evaluated.
     *
     * @return \Closure(array<mixed>): mixed
     */
    private function compileAggCond(mixed $operands): \Closure
    {
        if (!is_array($operands) || array_is_list($operands)) {
            throw new SyntaxError('$cond requires an object with if, then, else properties');
        }

        if (!array_key_exists('if', $operands) || !array_key_exists('then', $operands) || !array_key_exists('else', $operands)) {
            throw new SyntaxError('$cond requires if, then, and else properties');
        }

        $if = $this->compileAggPart('if', $operands['if']);
        $then = $this->compileAggPart('then', $operands['then']);
        $else = $this->compileAggPart('else', $operands['else']);

        return static fn(array $data): mixed => $if($data) ? $then($data) : $else($data);
    }

    /**
     * $size: https://www.mongodb.com/docs/manual/reference/operator/aggregation/size/
     *
     * Syntax: { $size: <expression> }
     *
     * @return \Closure(array<mixed>): int
     */
    private function compileAggSize(mixed $operand): \Closure
    {
        $inner = $this->compileAgg($operand);

        return static function (array $data) use ($inner): int {
            $value = $inner($data);
            if (!is_array($value) || !array_is_list($value)) {
                throw new SyntaxError('$size: argument must resolve to an array');
            }
            return count($value);
        };
    }

    /**
     * $toLower: https://www.mongodb.com/docs/manual/reference/operator/aggregation/toLower/
     * $toUpper: https://www.mongodb.com/docs/manual/reference/operator/aggregation/toUpper/
     *
     * Syntax: { $toLower: <expression> }
     *
     * @param \Closure(string): string $convert
     * @return \Closure(array<mixed>): string
     */
    private function compileAggCase(mixed $operand, string $operator, \Closure $convert): \Closure
    {
        $inner = $this->compileAgg($operand);

        return static function (array $data) use ($inner, $operator, $convert): string {
            $value = $inner($data);
            if (!is_string($value)) {
                throw new SyntaxError("{$operator}: argument must resolve to a string");
            }
            return $convert($value);
        };
    }

    /**
     * $substr:      https://www.mongodb.com/docs/manual/reference/operator/aggregation/substr/ (codepoints)
     * $substrBytes: https://www.mongodb.com/docs/manual/reference/operator/aggregation/substrBytes/ (bytes)
     *
     * Syntax: { $substr: [ <string>, <start>, <length> ] }
     *
     * @param \Closure(string, int, int|null): string $substring
     * @return \Closure(array<mixed>): string
     */
    private function compileAggSubstr(mixed $operands, string $operator, \Closure $substring): \Closure
    {
        if (!is_array($operands) || !array_is_list($operands) || count($operands) !== 3) {
            throw new SyntaxError("{$operator} requires an array of [string, start, length]");
        }

        [$stringNode, $startNode, $lengthNode] = $this->compileAggItems($operands);

        return static function (array $data) use ($stringNode, $startNode, $lengthNode, $operator, $substring): string {
            $string = $stringNode($data);
            $start = $startNode($data);
            $length = $lengthNode($data);

            if (!is_string($string)) {
                throw new SyntaxError("{$operator}: first argument must resolve to a string");
            }
            if (!is_int($start)) {
                throw new SyntaxError("{$operator}: second argument must resolve to an integer");
            }
            if (!is_int($length)) {
                throw new SyntaxError("{$operator}: third argument must resolve to an integer");
            }

            return $substring($string, $start, $length < 0 ? null : $length);
        };
    }

    /**
     * $strLenBytes: https://www.mongodb.com/docs/manual/reference/operator/aggregation/strLenBytes/
     * $strLenCP:    https://www.mongodb.com/docs/manual/reference/operator/aggregation/strLenCP/
     *
     * Syntax: { $strLenBytes: <expression> }
     *
     * @param \Closure(string): int $length
     * @return \Closure(array<mixed>): int
     */
    private function compileAggStrLen(mixed $operand, string $operator, \Closure $length): \Closure
    {
        $inner = $this->compileAgg($operand);

        return static function (array $data) use ($inner, $operator, $length): int {
            $value = $inner($data);
            if (!is_string($value)) {
                throw new SyntaxError("{$operator}: argument must resolve to a string");
            }
            return $length($value);
        };
    }

    /**
     * $trim:  https://www.mongodb.com/docs/manual/reference/operator/aggregation/trim/
     * $ltrim: https://www.mongodb.com/docs/manual/reference/operator/aggregation/ltrim/
     * $rtrim: https://www.mongodb.com/docs/manual/reference/operator/aggregation/rtrim/
     *
     * Syntax: { $trim: { input: <string>, chars: <string> } }
     *
     * @param \Closure(string, string=): string $trim
     * @return \Closure(array<mixed>): string
     */
    private function compileAggTrim(mixed $operand, string $operator, \Closure $trim): \Closure
    {
        if (!is_array($operand) || array_is_list($operand)) {
            throw new SyntaxError("{$operator} requires an object with 'input' property");
        }

        if (!array_key_exists('input', $operand)) {
            throw new SyntaxError("{$operator} requires 'input' property");
        }

        $inputNode = $this->compileAggPart('input', $operand['input']);
        $charsNode = array_key_exists('chars', $operand) ? $this->compileAggPart('chars', $operand['chars']) : null;

        return static function (array $data) use ($inputNode, $charsNode, $operator, $trim): string {
            $input = $inputNode($data);
            if (!is_string($input)) {
                throw new SyntaxError("{$operator}: 'input' must resolve to a string");
            }

            if ($charsNode === null) {
                return $trim($input);
            }

            $chars = $charsNode($data);
            if (!is_string($chars)) {
                throw new SyntaxError("{$operator}: 'chars' must resolve to a string");
            }
            return $trim($input, $chars);
        };
    }

    /**
     * $concat: https://www.mongodb.com/docs/manual/reference/operator/aggregation/concat/
     *
     * Syntax: { $concat: [ <expression1>, <expression2>, ... ] }
     *
     * @return \Closure(array<mixed>): string
     */
    private function compileAggConcat(mixed $operands): \Closure
    {
        $items = $this->compileList($operands, '$concat requires an array of string expressions');

        return static function (array $data) use ($items): string {
            $result = '';
            foreach ($items as $item) {
                $value = $item($data);
                if (!is_string($value)) {
                    throw new SyntaxError('$concat: all arguments must resolve to strings');
                }
                $result .= $value;
            }
            return $result;
        };
    }
}
