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

    // Arithmetic operators
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

    private function evalAggSubtract(mixed $operands, array $data): int|float
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$subtract', $data);

        if (!is_numeric($value1) || !is_numeric($value2)) {
            throw new SyntaxError('$subtract operands must be numeric');
        }

        return $value1 - $value2;
    }

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

    // Comparison operators for aggregation expressions
    private function evalAggEq(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$eq', $data);
        return $this->isEqual($value1, $value2);
    }

    private function evalAggNe(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$ne', $data);
        return !$this->isEqual($value1, $value2);
    }

    private function evalAggGt(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$gt', $data);
        return $value1 > $value2;
    }

    private function evalAggGte(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$gte', $data);
        return $value1 >= $value2;
    }

    private function evalAggLt(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$lt', $data);
        return $value1 < $value2;
    }

    private function evalAggLte(mixed $operands, array $data): bool
    {
        [$value1, $value2] = $this->getBinaryOperands($operands, '$lte', $data);
        return $value1 <= $value2;
    }

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
}