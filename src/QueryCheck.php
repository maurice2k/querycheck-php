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
    private array $logicalOperators;
    private array $queryOperators;
    private array $aggregationOperators;
    private bool $undefinedEqualsNull = false;
    private bool $strictMode = false;
    private ?\Closure $operandEvaluator = null;

    public function __construct(array $query)
    {
        $this->query = $query;

        $this->logicalOperators = [
            '$or' => $this->evalOr(...),
            '$and' => $this->evalAnd(...),
            '$expr' => $this->evalExpr(...),
        ];

        $this->queryOperators = [
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

        // these can be used within $expr
        $this->aggregationOperators = [
            '$add' => $this->evalAggAdd(...),
            '$subtract' => $this->evalAggSubtract(...),
            '$multiply' => $this->evalAggMultiply(...),
            '$divide' => $this->evalAggDivide(...),
            '$mod' => $this->evalAggMod(...),
            '$eq' => $this->evalAggEq(...),
            '$ne' => $this->evalAggNe(...),
            '$gt' => $this->evalAggGt(...),
            '$gte' => $this->evalAggGte(...),
            '$lt' => $this->evalAggLt(...),
            '$lte' => $this->evalAggLte(...),
            '$not' => $this->evalAggNot(...),
            '$in' => $this->evalAggIn(...),
            '$cond' => $this->evalAggCond(...),
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
            // we assume an implicit $and structure, e.g.
            // {
            //   "age": 37,
            //   "name": "John"
            // }
            // which is equivalent to:
            // {
            //   "$and": [
            //     {"age": 37},
            //     {"name": "John"}
            //   ]
            // }
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
        $firstKey = $keys[0];
        $value = $query[$firstKey];

        if ($firstKey === '') {
            throw new SyntaxError('Empty keys are not supported!');
        }

        if ($firstKey[0] === '$') {
            // "key" must be a boolean operator like $and/$or, "value" the
            // sub queries to parse and evaluate
            $booleanOperator = $this->logicalOperators[$firstKey] ?? null;

            if (!is_callable($booleanOperator)) {
                throw new SyntaxError("Unsupported boolean operator: {$firstKey}");
            }

            return $booleanOperator($value, $data);
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

            $variableValue = $this->getVariableValue($firstKey, $data);
            return $this->evalQueryExpression($firstKey, $variableValue, $value, $data);
        }
    }

    private function evalQueryExpression(string $variableName, mixed $variableValue, mixed $expression, array $data): bool
    {
        if (is_array($expression) && array_is_list($expression) || $expression === null || !is_array($expression)) {
            // expression is of type array, null, number, string, bool; wrap it
            $expression = ['$eq' => $expression];
        } elseif (is_array($expression)) {
            // expression is an object, let's check if it's some kind of supported {$operator: operand} object
            // and wrap it otherwise
            $keys = array_keys($expression);
            if (count($keys) === 0 || !isset($this->queryOperators[$keys[0]])) {
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

            $queryOperator = $this->queryOperators[$operator] ?? null;

            if (!is_callable($queryOperator)) {
                throw new SyntaxError("Unsupported query operator: {$operator}");
            }

            $result = $queryOperator($variableName, $variableValue, $operand, $expression) && $result;
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

    /**
     * Evaluates the $or logical operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/or/
     *
     * Performs a logical OR operation on an array of expressions and selects
     * the documents that satisfy at least one of the expressions.
     *
     * Syntax: { $or: [ <expression1>, <expression2>, ... ] }
     *
     * @param mixed $query Array of query expressions
     * @param array $data The data context
     * @return bool True if at least one expression matches, false otherwise
     */
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

    /**
     * Evaluates the $and logical operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/and/
     *
     * Performs a logical AND operation on an array of expressions and selects
     * the documents that satisfy all the expressions.
     *
     * Syntax: { $and: [ <expression1>, <expression2>, ... ] }
     *
     * @param mixed $query Array of query expressions
     * @param array $data The data context
     * @return bool True if all expressions match, false otherwise
     */
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

    /**
     * Evaluates the $eq query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/eq/
     *
     * Matches documents where the value of a field equals the specified value.
     *
     * Syntax: { <field>: { $eq: <value> } } or shorthand: { <field>: <value> }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if values are equal, false otherwise
     */
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

    /**
     * Evaluates the $ne query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/ne/
     *
     * Matches documents where the value of a field is not equal to the specified value.
     *
     * Syntax: { <field>: { $ne: <value> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if values are not equal, false otherwise
     */
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

    /**
     * Evaluates the $gt query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/gt/
     *
     * Matches documents where the value of a field is greater than the specified value.
     *
     * Syntax: { <field>: { $gt: <value> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if field value is greater than operand, false otherwise
     */
    private function evalGt(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$gt: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }

        return $variableValue > $operand;
    }

    /**
     * Evaluates the $gte query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/gte/
     *
     * Matches documents where the value of a field is greater than or equal to the specified value.
     *
     * Syntax: { <field>: { $gte: <value> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if field value is greater than or equal to operand, false otherwise
     */
    private function evalGte(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$gte: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue >= $operand;
    }

    /**
     * Evaluates the $lt query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/lt/
     *
     * Matches documents where the value of a field is less than the specified value.
     *
     * Syntax: { <field>: { $lt: <value> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if field value is less than operand, false otherwise
     */
    private function evalLt(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$lt: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue < $operand;
    }

    /**
     * Evaluates the $lte query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/lte/
     *
     * Matches documents where the value of a field is less than or equal to the specified value.
     *
     * Syntax: { <field>: { $lte: <value> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The value to compare against
     * @param mixed $expression The full expression context
     * @return bool True if field value is less than or equal to operand, false otherwise
     */
    private function evalLte(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        if ($this->strictMode && gettype($variableValue) !== gettype($operand) && $variableValue !== null) {
            throw new StrictTypeError("\$lte: variable {$variableName} is of type " . gettype($variableValue) . " while operand is of type " . gettype($operand));
        }
        return $variableValue <= $operand;
    }

    /**
     * Evaluates the $in query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/in/
     *
     * Matches documents where the value of a field equals any value in the specified array.
     *
     * Syntax: { <field>: { $in: [ <value1>, <value2>, ... ] } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand Array of values to match against
     * @param mixed $expression The full expression context
     * @return bool True if field value is in the array, false otherwise
     */
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

    /**
     * Evaluates the $regex query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/regex/
     *
     * Provides regular expression capabilities for pattern matching strings in queries.
     *
     * Syntax: { <field>: { $regex: <pattern>, $options: <options> } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The regex pattern
     * @param mixed $expression The full expression context (may contain $options)
     * @return bool True if pattern matches, false otherwise
     */
    private function evalRegExp(string $variableName, mixed $variableValue, mixed $operand, mixed $expression): bool
    {
        $options = $expression['$options'] ?? null;
        $pattern = '/' . str_replace('/', '\\/', (string)$operand) . '/';
        if ($options !== null) {
            $pattern .= $options;
        }
        return (bool)preg_match($pattern, (string)$variableValue);
    }

    /**
     * Helper method that always returns true
     *
     * Used for operators like $options that don't need evaluation themselves.
     *
     * @param string $variableName The field name (unused)
     * @param mixed $variableValue The field value (unused)
     * @param mixed $operand The operand (unused)
     * @param mixed $expression The expression (unused)
     * @return bool Always returns true
     */
    private function evalTrue(string $variableName = '', mixed $variableValue = null, mixed $operand = null, mixed $expression = null): bool
    {
        return true;
    }

    /**
     * Evaluates the $not query operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/not/
     *
     * Performs a logical NOT operation on the specified operator expression.
     *
     * Syntax: { <field>: { $not: { <operator-expression> } } }
     *
     * @param string $variableName The field name being compared
     * @param mixed $variableValue The actual field value
     * @param mixed $operand The operator expression to negate
     * @param mixed $expression The full expression context
     * @return bool The negated result of the operator expression
     */
    private function evalNot(string $variableName, mixed $variableValue, mixed $operand, mixed $expression = null): bool
    {
        return !$this->evalQueryExpression($variableName, $variableValue, $operand, []);
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

    /**
     * Evaluates the $expr operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/query/expr/
     *
     * Allows the use of aggregation expressions within the query language.
     *
     * Syntax: { $expr: <aggregation expression> }
     *
     * @param mixed $expression Aggregation expression to evaluate
     * @param array $data The data context
     * @return bool The boolean result of the expression
     */
    private function evalExpr(mixed $expression, array $data): bool
    {
        if (!is_array($expression) || array_is_list($expression)) {
            throw new SyntaxError('$expr requires an object expression');
        }

        $result = $this->evalAggExpression($expression, $data);

        // Convert result to boolean
        return (bool)$result;
    }

    private function evalAggExpression(mixed $expression, array $data): mixed
    {
        // Allow operandEvaluator to transform expressions first
        if ($this->operandEvaluator !== null) {
            $transformedExpression = ($this->operandEvaluator)($expression, $data);
            // If the evaluator returned something different, use it
            if ($transformedExpression !== $expression) {
                $expression = $transformedExpression;
            }
        }

        // Handle field references (strings starting with $)
        if (is_string($expression) && str_starts_with($expression, '$')) {
            $fieldName = substr($expression, 1);
            try {
                return $this->getVariableValue($fieldName, $data);
            } catch (UnknownVariableException $e) {
                if ($this->undefinedEqualsNull) {
                    return null;
                }
                throw $e;
            }
        }

        // Handle literal values
        if (!is_array($expression)) {
            return $expression;
        }

        // Handle arrays (lists)
        if (array_is_list($expression)) {
            return array_map(fn($item) => $this->evalAggExpression($item, $data), $expression);
        }

        // Handle aggregation operators
        $keys = array_keys($expression);
        if (count($keys) === 0) {
            return $expression;
        }

        $firstKey = $keys[0];

        // Check if it's an aggregation operator
        if (str_starts_with($firstKey, '$')) {
            $operator = $this->aggregationOperators[$firstKey] ?? null;

            if (!is_callable($operator)) {
                throw new SyntaxError("Unsupported aggregation operator: {$firstKey}");
            }

            return $operator($expression[$firstKey], $data);
        }

        // Return as-is if not an operator
        return $expression;
    }

    /**
     * Helper method to extract exactly 2 operands for binary operators
     * @return array{mixed, mixed}
     */
    private function getBinaryOperands(mixed $operands, string $operator, array $data): array
    {
        if (!is_array($operands) || !array_is_list($operands) || count($operands) !== 2) {
            throw new SyntaxError("{$operator} requires an array of exactly 2 operands");
        }

        return [
            $this->evalAggExpression($operands[0], $data),
            $this->evalAggExpression($operands[1], $data)
        ];
    }

    // Aggregation operators

    /**
     * Evaluates the $add aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/add/
     *
     * Adds numbers together or adds numbers and a date. If one of the arguments is a date,
     * $add treats the other arguments as milliseconds to add to the date.
     *
     * Syntax: { $add: [ <expression1>, <expression2>, ... ] }
     *
     * @param mixed $operands Array of numeric expressions to add
     * @param array $data The data context
     * @return int|float The sum of all operands
     */
    private function evalAggAdd(mixed $operands, array $data): int|float
    {
        if (!is_array($operands) || !array_is_list($operands)) {
            throw new SyntaxError('$add requires an array of operands');
        }

        $sum = 0;
        foreach ($operands as $operand) {
            $value = $this->evalAggExpression($operand, $data);
            if (!is_numeric($value)) {
                throw new SyntaxError('$add operands must be numeric');
            }
            $sum += $value;
        }
        return $sum;
    }

    /**
     * Evaluates the $subtract aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/subtract/
     *
     * Subtracts two numbers to return the difference, or two dates to return the difference
     * in milliseconds, or a date and a number in milliseconds to return the resulting date.
     *
     * Syntax: { $subtract: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two numeric expressions
     * @param array $data The data context
     * @return int|float The difference between the two operands
     */
    private function evalAggSubtract(mixed $operands, array $data): int|float
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$subtract', $data);

        if (!is_numeric($value1) || !is_numeric($value2)) {
            throw new SyntaxError('$subtract operands must be numeric');
        }

        return $value1 - $value2;
    }

    /**
     * Evaluates the $multiply aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/multiply/
     *
     * Multiplies numbers together and returns the product.
     *
     * Syntax: { $multiply: [ <expression1>, <expression2>, ... ] }
     *
     * @param mixed $operands Array of numeric expressions to multiply
     * @param array $data The data context
     * @return int|float The product of all operands
     */
    private function evalAggMultiply(mixed $operands, array $data): int|float
    {
        if (!is_array($operands) || !array_is_list($operands)) {
            throw new SyntaxError('$multiply requires an array of operands');
        }

        $product = 1;
        foreach ($operands as $operand) {
            $value = $this->evalAggExpression($operand, $data);
            if (!is_numeric($value)) {
                throw new SyntaxError('$multiply operands must be numeric');
            }
            $product *= $value;
        }
        return $product;
    }

    /**
     * Evaluates the $divide aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/divide/
     *
     * Divides one number by another and returns the result.
     *
     * Syntax: { $divide: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two numeric expressions
     * @param array $data The data context
     * @return int|float The quotient of the division
     */
    private function evalAggDivide(mixed $operands, array $data): int|float
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$divide', $data);

        if (!is_numeric($value1) || !is_numeric($value2)) {
            throw new SyntaxError('$divide operands must be numeric');
        }

        if ($value2 == 0) {
            throw new SyntaxError('$divide: division by zero');
        }

        return $value1 / $value2;
    }

    /**
     * Evaluates the $mod aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/mod/
     *
     * Divides one number by another and returns the remainder.
     *
     * Syntax: { $mod: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two numeric expressions
     * @param array $data The data context
     * @return int|float The remainder of the division
     */
    private function evalAggMod(mixed $operands, array $data): int|float
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$mod', $data);

        if (!is_numeric($value1) || !is_numeric($value2)) {
            throw new SyntaxError('$mod operands must be numeric');
        }

        if ($value2 == 0) {
            throw new SyntaxError('$mod: division by zero');
        }

        return $value1 % $value2;
    }

    /**
     * Evaluates the $eq aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/eq/
     *
     * Compares two values and returns true if they are equivalent, false otherwise.
     *
     * Syntax: { $eq: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if values are equal, false otherwise
     */
    private function evalAggEq(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$eq', $data);
        return $this->isEqual($value1, $value2);
    }

    /**
     * Evaluates the $ne aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/ne/
     *
     * Compares two values and returns true if they are not equivalent, false otherwise.
     *
     * Syntax: { $ne: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if values are not equal, false otherwise
     */
    private function evalAggNe(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$ne', $data);
        return !$this->isEqual($value1, $value2);
    }

    /**
     * Evaluates the $gt aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/gt/
     *
     * Compares two values and returns true if the first value is greater than the second.
     *
     * Syntax: { $gt: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if first value is greater than second, false otherwise
     */
    private function evalAggGt(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$gt', $data);
        return $value1 > $value2;
    }

    /**
     * Evaluates the $gte aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/gte/
     *
     * Compares two values and returns true if the first value is greater than or equal to the second.
     *
     * Syntax: { $gte: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if first value is greater than or equal to second, false otherwise
     */
    private function evalAggGte(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$gte', $data);
        return $value1 >= $value2;
    }

    /**
     * Evaluates the $lt aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/lt/
     *
     * Compares two values and returns true if the first value is less than the second.
     *
     * Syntax: { $lt: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if first value is less than second, false otherwise
     */
    private function evalAggLt(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$lt', $data);
        return $value1 < $value2;
    }

    /**
     * Evaluates the $lte aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/lte/
     *
     * Compares two values and returns true if the first value is less than or equal to the second.
     *
     * Syntax: { $lte: [ <expression1>, <expression2> ] }
     *
     * @param mixed $operands Array with exactly two expressions
     * @param array $data The data context
     * @return bool True if first value is less than or equal to second, false otherwise
     */
    private function evalAggLte(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$lte', $data);
        return $value1 <= $value2;
    }

    /**
     * Evaluates the $cond aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/cond/
     *
     * Evaluates a boolean expression to return one of two specified return expressions.
     *
     * Syntax: { $cond: { if: <boolean-expression>, then: <true-case>, else: <false-case> } }
     *
     * @param mixed $operands Object with 'if', 'then', and 'else' properties
     * @param array $data The data context
     * @return mixed The result of either 'then' or 'else' expression
     */
    private function evalAggCond(mixed $operands, array $data): mixed
    {
        if (!is_array($operands) || array_is_list($operands)) {
            throw new SyntaxError('$cond requires an object with if, then, else properties');
        }

        if (!isset($operands['if']) || !isset($operands['then']) || !isset($operands['else'])) {
            throw new SyntaxError('$cond requires if, then, and else properties');
        }

        $condition = $this->evalAggExpression($operands['if'], $data);

        if ($condition) {
            return $this->evalAggExpression($operands['then'], $data);
        } else {
            return $this->evalAggExpression($operands['else'], $data);
        }
    }

    /**
     * Evaluates the $not aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/not/
     *
     * Evaluates a boolean and returns the opposite boolean value.
     * When passed an expression that evaluates to true, $not returns false;
     * when passed an expression that evaluates to false, $not returns true.
     *
     * In addition to the false boolean value, $not evaluates as false the following:
     * null, 0, and undefined values. The $not evaluates all other values as true,
     * including non-zero numeric values and arrays.
     *
     * Syntax: { $not: [ <expression> ] }
     *
     * @param mixed $operands Array with exactly one expression
     * @param array $data The data context
     * @return bool The negated boolean result
     */
    private function evalAggNot(mixed $operands, array $data): bool
    {
        if (!is_array($operands) || !array_is_list($operands) || count($operands) !== 1) {
            throw new SyntaxError('$not requires an array with exactly one expression');
        }

        $value = $this->evalAggExpression($operands[0], $data);

        // MongoDB behavior: false, null, 0, and undefined evaluate as false
        // All other values (including non-zero numbers and arrays) evaluate as true
        if ($value === false || $value === null || $value === 0) {
            return true;
        }

        return false;
    }

    /**
     * Evaluates the $in aggregation operator
     *
     * MongoDB Spec: https://www.mongodb.com/docs/manual/reference/operator/aggregation/in/
     *
     * Returns a boolean indicating whether a specified value is in an array.
     *
     * Syntax: { $in: [ <expression>, <array expression> ] }
     *
     * @param mixed $operands Array with exactly two elements: [value, array]
     * @param array $data The data context
     * @return bool True if value is found in array, false otherwise
     */
    private function evalAggIn(mixed $operands, array $data): bool
    {
        [$searchValue, $arrayValue] = $this->getBinaryOperands($operands, '$in', $data);

        if (!is_array($arrayValue) || !array_is_list($arrayValue)) {
            throw new SyntaxError('$in: second operand must be an array');
        }

        foreach ($arrayValue as $item) {
            if ($this->isEqual($searchValue, $item)) {
                return true;
            }
        }

        return false;
    }
}