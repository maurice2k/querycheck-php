<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck;

use Maurice2k\QueryCheck\Exception\SyntaxError;
use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;

/**
 * Query check
 *
 * @author Moritz Fain <moritz@fain.io>
 */
class QueryCheck
{
    private array $query;
    private array $booleanOperators;
    private array $expressionOperators;
    private bool $undefinedEqualsNull = false;
    private bool $strictMode = false;
    private ?\Closure $operandEvaluator = null;

    public function __construct(array $query)
    {
        $this->query = $query;

        $this->booleanOperators = [
            '$or' => $this->evalOr(...),
            '$and' => $this->evalAnd(...),
        ];

        $this->expressionOperators = [
            '$eq' => $this->evalEq(...),
            '$ne' => $this->evalNe(...),
            '$gt' => $this->evalGt(...),
            '$gte' => $this->evalGte(...),
            '$lt' => $this->evalLt(...),
            '$lte' => $this->evalLte(...),
            '$in' => $this->evalIn(...),
            '$regex' => $this->evalRegExp(...),
            '$options' => $this->evalTrue(...),
            '$not' => $this->evalNot(...),
        ];
    }

    public function setUndefinedEqualsNull(bool $equalsNull): void
    {
        $this->undefinedEqualsNull = $equalsNull;
    }

    public function setStrictMode(bool $strictMode): void
    {
        $this->strictMode = $strictMode;
    }

    public function setOperandEvaluator(callable $fn): void
    {
        $this->operandEvaluator = $fn(...);
    }

    public function test(mixed $data): bool
    {
        if ($data === null || !is_array($data) || array_is_list($data)) {
            if ($this->strictMode) {
                throw new StrictTypeError('Input data must be a hash/object');
            }
            return false;
        }

        return $this->evalQuery($this->query, $data);
    }

    private function evalQuery(mixed $query, array $data): bool
    {
        if (!is_array($query)) {
            throw new SyntaxError("Query must be an object: " . json_encode($query));
        }

        $keys = array_keys($query);
        if (count($keys) > 1) {
            // implicit $and structure; re-format
            $andQuery = [];
            foreach ($keys as $k) {
                $partial = [];
                $partial[$k] = $query[$k];
                $andQuery[] = $partial;
            }

            return $this->evalAnd($andQuery, $data);
        }

        if (count($keys) === 0) {
            // empty query is always valid
            return true;
        }

        // we have exactly one key
        $key = $keys[0];
        $value = $query[$key];

        if ($key === '') {
            throw new SyntaxError('Empty keys are not supported!');
        }

        if ($key[0] === '$') {
            // "key" must be a boolean operator like $and/$or, "value" the
            // sub queries to parse and evaluate
            $booleanParser = $this->booleanOperators[$key] ?? null;

            if (!is_callable($booleanParser)) {
                throw new SyntaxError("Unsupported boolean operator: {$key}");
            }

            return $booleanParser($value, $data);
        } else {
            // "key" is a variable name; "value" the expression (or a set of
            // expressions) to parse and evaluate.

            // the default style of an expression is as follows:
            // {age: {$eq: 37}}

            // additionally, the following shortcut styles are also supported:
            // {age: 37}      == {age: {$eq: 37}}
            // {age: null}    == {age: {$eq: null}}
            // {age: {$gt: 30, $lt: 40}}
            //                == {$and: [{age: {$gt: 30}}, {age: {$lt: 40}}]}

            $variableValue = $this->getVariableValue($key, $data);
            return $this->evalExpression($key, $variableValue, $value, $data);
        }
    }

    private function evalExpression(string $variableName, mixed $variableValue, mixed $expression, array $data): bool
    {
        if (is_array($expression) && array_is_list($expression) || $expression === null || !is_array($expression)) {
            // expression is of type array, null, number, string, bool; wrap it
            $expression = ['$eq' => $expression];
        } elseif (is_array($expression)) {
            // expression is an object, let's check if it's some kind of supported {$operator: operand} object
            // and wrap it otherwise
            $keys = array_keys($expression);
            if (count($keys) === 0 || !isset($this->expressionOperators[$keys[0]])) {
                $expression = ['$eq' => $expression];
            }
        } else {
            throw new SyntaxError('Unsupported expression: ' . json_encode($expression));
        }

        $result = true;
        $operators = array_keys($expression);
        foreach ($operators as $operator) {
            $operand = $expression[$operator];
            if ($this->operandEvaluator !== null) {
                $operand = ($this->operandEvaluator)($operand, $data);
            }

            $expressionParser = $this->expressionOperators[$operator] ?? null;

            if (!is_callable($expressionParser)) {
                throw new SyntaxError("Unsupported expression operator: {$operator}");
            }

            $result = $expressionParser($variableName, $variableValue, $operand, $expression) && $result;
        }
        return $result;
    }

    public function getVariableValue(string $variableName, array $data): mixed
    {
        $str = str_replace('[', '.[', $variableName);
        preg_match_all('/(\\\.|[^.]+?)+/', $str, $matches);
        $parts = $matches[0];

        foreach ($parts as $part) {
            if (preg_match('/\[(\d+)\]$/', $part, $matches)) {
                $idx = (int)$matches[1];
                if (!is_array($data) || !isset($data[$idx])) {
                    // Variable doesn't exist (undefined in JS terms)
                    if ($this->undefinedEqualsNull) {
                        return null;
                    }
                    throw new UnknownVariableException("Variable '{$variableName}' is not defined");
                }
                $data = $data[$idx];
            } else {
                if (!is_array($data) || !array_key_exists($part, $data)) {
                    // Variable doesn't exist (undefined in JS terms)
                    if ($this->undefinedEqualsNull) {
                        return null;
                    }
                    throw new UnknownVariableException("Variable '{$variableName}' is not defined");
                }
                $data = $data[$part];
            }

            if ($data === null) {
                break;
            }
        }

        return $data;
    }

    private function evalOr(mixed $query, array $data): bool
    {
        if (!is_array($query) || !array_is_list($query)) {
            throw new SyntaxError('$or can only operate on arrays of queries');
        }

        $result = false;
        foreach ($query as $item) {
            $result = $this->evalQuery($item, $data) || $result;
        }

        return $result;
    }

    private function evalAnd(mixed $query, array $data): bool
    {
        if (!is_array($query) || !array_is_list($query)) {
            throw new SyntaxError('$and can only operate on arrays of queries');
        }

        $result = true;
        foreach ($query as $item) {
            // keep this sorting ('&& $result' at the end)
            $result = $this->evalQuery($item, $data) && $result;
        }

        return $result;
    }

    private function evalEq(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if (gettype($variableValue) === gettype($operand) && !is_array($variableValue) && !is_object($variableValue)) {
            // boolean, number, string
            return $variableValue === $operand;
        }

        if ($variableValue === null || $operand === null) {
            // either variableValue or operand are null or both are null
            return $variableValue === $operand;
        }

        if (is_array($variableValue) && array_is_list($variableValue)) {
            if (is_array($operand) && array_is_list($operand)) {
                if ($this->isEqual($variableValue, $operand)) {
                    return true;
                }
            }

            return $this->evalIn($variableName, $operand, $variableValue);
        }

        if (is_array($variableValue) && is_array($operand)) {
            return $this->isEqualObject($variableValue, $operand);
        }

        if ($this->strictMode) {
            throw new StrictTypeError("\$eq: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }

        if (is_string($variableValue) && is_numeric($operand)) {
            return $variableValue === (string)$operand;
        } elseif (is_numeric($variableValue) && is_string($operand)) {
            return (string)$variableValue === $operand;
        }

        return false;
    }

    private function evalNe(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if (!$this->strictMode) {
            if (is_string($variableValue) && is_numeric($operand)) {
                return $variableValue !== (string)$operand;
            } elseif (is_numeric($variableValue) && is_string($operand)) {
                return (string)$variableValue !== $operand;
            }
        }

        if (gettype($variableValue) !== gettype($operand)) {
            return true;
        }

        return !$this->isEqual($variableValue, $operand);
    }

    private function evalGt(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$gt: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }

        return $variableValue > $operand;
    }

    private function evalGte(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$gte: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue >= $operand;
    }

    private function evalLt(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$lt: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue < $operand;
    }

    private function evalLte(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$lte: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue <= $operand;
    }

    private function evalIn(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if (!is_array($operand) || !array_is_list($operand)) {
            if ($this->strictMode) {
                throw new StrictTypeError("\$in: variable {$variableName}: operand must be of type array but is of type " . gettype($operand));
            }
            return false;
        }

        foreach ($operand as $item) {
            if ($this->isEqual($variableValue, $item)) {
                return true;
            }
        }
        return false;
    }

    private function evalRegExp(string $variableName, mixed $variableValue, mixed $operand, mixed $expression): bool
    {
        $options = $expression['$options'] ?? null;
        $pattern = '/' . str_replace('/', '\\/', (string)$operand) . '/';
        if ($options !== null) {
            $pattern .= $options;
        }
        return (bool)preg_match($pattern, (string)$variableValue);
    }

    private function evalTrue(string $variableName = '', mixed $variableValue = null, mixed $operand = null, mixed $expression = null): bool
    {
        return true;
    }

    private function evalNot(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        return !$this->evalExpression($variableName, $variableValue, $operand, []);
    }

    private function isEqual(mixed $a, mixed $b): bool
    {
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

        if (is_string($a) && is_numeric($b)) {
            return $a === (string)$b;
        } elseif (is_numeric($a) && is_string($b)) {
            return (string)$a === $b;
        }

        return false;
    }

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
}