<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck;

use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;

/**
 * Query check
 *
 * The query is compiled into a tree of closures on the first call to test()
 * (see Compiler) and reused for every following call. Changing a setting
 * discards the compiled query; it is rebuilt on the next test().
 *
 * @author Moritz Fain <moritz@fain.io>
 */
class QueryCheck
{
    /** @var array<mixed> */
    private array $query;
    private bool $throwOnUnknownVariable = true;
    private bool $strictMode = false;
    private ?\Closure $operandEvaluator = null;
    /** @var (\Closure(array<mixed>): bool)|null */
    private ?\Closure $compiled = null;

    /** @param array<mixed> $query */
    public function __construct(array $query)
    {
        $this->query = $query;
    }

    /**
     * When enabled (the default), a missing field throws
     * UnknownVariableException. When disabled, a missing field compares as
     * null, so {field: null} matches "null or missing", as in MongoDB.
     * An existing field with the value null is never unknown.
     */
    public function setThrowOnUnknownVariable(bool $throwOnUnknown): void
    {
        $this->throwOnUnknownVariable = $throwOnUnknown;
        $this->compiled = null;
    }

    public function setStrictMode(bool $strictMode): void
    {
        $this->strictMode = $strictMode;
        $this->compiled = null;
    }

    public function setOperandEvaluator(callable $fn): void
    {
        $this->operandEvaluator = $fn(...);
        $this->compiled = null;
    }

    public function test(mixed $data): bool
    {
        if ($data === null || !is_array($data) || array_is_list($data)) {
            if ($this->strictMode) {
                throw new StrictTypeError('Input data must be a hash/object');
            }
            return false;
        }

        return ($this->compile())($data);
    }

    /**
     * Compiles the query now and throws a SyntaxError if it is invalid, with
     * the location in the message ("$or[1].age.$gtt: Unsupported query
     * operator: $gtt").
     *
     * test() compiles lazily on its first call, so a broken query would
     * otherwise only surface with the first document. Call validate() where
     * queries are loaded or saved, after the setters: changing a setting
     * discards the compiled query. The compiled query is kept, so the next
     * test() does not compile again.
     *
     * Errors that depend on the document (types, missing fields, values that
     * $add cannot add) are only found by test(). With an operand evaluator,
     * parts of $expr and $not are only compiled when a document reaches them.
     *
     * @throws Exception\SyntaxError
     */
    public function validate(): void
    {
        $this->compile();
    }

    /**
     * @return \Closure(array<mixed>): bool
     */
    private function compile(): \Closure
    {
        return $this->compiled ??= (new Compiler(
            $this->strictMode,
            $this->throwOnUnknownVariable,
            $this->operandEvaluator,
        ))->compileQuery($this->query);
    }

    /**
     * Resolves a field path like "customer.address.city" or "items[0].name"
     * against $data, using this instance's setThrowOnUnknownVariable() setting.
     *
     * @param array<mixed> $data
     */
    public function getVariableValue(string $variableName, array $data): mixed
    {
        $value = $data;

        foreach (Compiler::parsePath($variableName) as [$isIndex, $key]) {
            if ($isIndex) {
                if (!is_array($value) || !isset($value[$key])) {
                    // Variable doesn't exist (undefined in JS terms)
                    return $this->handleUnknownVariable($variableName);
                }
            } elseif (!is_array($value) || !array_key_exists($key, $value)) {
                // Variable doesn't exist (undefined in JS terms)
                return $this->handleUnknownVariable($variableName);
            }

            $value = $value[$key];

            if ($value === null) {
                break;
            }
        }

        return $value;
    }

    private function handleUnknownVariable(string $variableName): mixed
    {
        if (!$this->throwOnUnknownVariable) {
            return null;
        }

        throw new UnknownVariableException("Variable '{$variableName}' is not defined");
    }
}
