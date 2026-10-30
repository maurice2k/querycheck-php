<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Tests;

use Maurice2k\QueryCheck\QueryCheck;
use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\SyntaxError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class CompiledQueryTest extends TestCase
{
    /**
     * @param array<mixed> $data
     */
    private static function identityOperand(mixed $operand, array $data): mixed
    {
        return $operand;
    }

    /**
     * @param array<mixed> $query
     */
    private function make(
        array $query,
        bool $withEvaluator = false,
        bool $throwOnUnknown = true,
        bool $strict = false,
    ): QueryCheck {
        $qc = new QueryCheck($query);
        $qc->setStrictMode($strict);
        $qc->setThrowOnUnknownVariable($throwOnUnknown);
        if ($withEvaluator) {
            $qc->setOperandEvaluator(self::identityOperand(...));
        }

        return $qc;
    }

    /**
     * Specialized closures (no operand evaluator) versus the generic path.
     *
     * @return array<string, array{bool}>
     */
    public static function codePathProvider(): array
    {
        return [
            'no evaluator' => [false],
            'identity evaluator' => [true],
        ];
    }

    // A. Ordering never matches array fields

    /**
     * @return array<string, array{array<mixed>, array<string, mixed>}>
     */
    public static function arrayFieldOrderingProvider(): array
    {
        return [
            '$gt number on list' => [['v' => ['$gt' => 10]], ['v' => [12, 13]]],
            '$gte number on list of float' => [['v' => ['$gte' => 10]], ['v' => [10.0]]],
            '$lt number on list of float' => [['v' => ['$lt' => 12]], ['v' => [10.0]]],
            '$lte number on list' => [['v' => ['$lte' => 12]], ['v' => [12, 13]]],
            'split range on list' => [['v' => ['$gt' => 10, '$lt' => 13]], ['v' => [5, 20]]],
            'split range on empty list' => [['v' => ['$gt' => 10, '$lt' => 13]], ['v' => []]],
            '$expr $gt on list' => [['$expr' => ['$gt' => ['$v', 10]]], ['v' => [12, 13]]],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<string, mixed> $document
     */
    #[TestDox('ordering does not match an array field')]
    #[DataProvider('arrayFieldOrderingProvider')]
    public function testOrderingDoesNotMatchArrayField(array $query, array $document): void
    {
        $this->assertFalse((new QueryCheck($query))->test($document));
    }

    #[TestDox('[STRICT] ordering an array field throws StrictTypeError')]
    public function testStrictOrderingArrayFieldThrows(): void
    {
        $qc = new QueryCheck(['v' => ['$gt' => 10]]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$gt: variable v is of type array while operand is of type integer');
        $qc->test(['v' => [12, 13]]);
    }

    #[TestDox('[STRICT] $expr ordering an array field throws StrictTypeError')]
    public function testStrictExprOrderingArrayFieldThrows(): void
    {
        $qc = new QueryCheck(['$expr' => ['$gt' => ['$v', 10]]]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$gt: first operand is of type array while second operand is of type integer');
        $qc->test(['v' => [12, 13]]);
    }

    // B. Specialized closures and generic implementations agree

    /**
     * Full cross product of values, operators and operands, in both modes.
     * Exception messages name the field ("variable v" vs "variable w.v");
     * outcome() normalizes that, so throwing cases are compared too.
     *
     * @return array<string, array{mixed, string, mixed, bool}>
     */
    public static function specializedAgreesWithGenericProvider(): array
    {
        $values = [
            12, 12.0, 12.1, -0.0, 0, '12', '12.0', '9', '10', 'abc', '', null, true, false,
            [12, 13], ['12'], [], [null], ['a' => 1], NAN, INF, -INF, 2 ** 53 + 1, (float)(2 ** 53),
        ];
        $operators = ['$eq', '$ne', '$gt', '$gte', '$lt', '$lte', '$in'];
        $operands = [12, 12.0, '12', '9', 'abc', null, true, NAN, (float)(2 ** 53), [12, 13]];

        /** @var array<string, array{mixed, string, mixed, bool}> $cases */
        $cases = [];
        foreach ([false, true] as $strict) {
            foreach ($values as $value) {
                foreach ($operators as $operator) {
                    foreach ($operands as $operand) {
                        $key = ($strict ? 'strict' : 'loose')
                            . ' ' . self::label($value)
                            . ' ' . $operator
                            . ' ' . self::label($operand);
                        $cases[$key] = [$value, $operator, $operand, $strict];
                    }
                }
            }
        }

        return $cases;
    }

    #[TestDox('specialized closures agree with generic implementations')]
    #[DataProvider('specializedAgreesWithGenericProvider')]
    public function testSpecializedAgreesWithGeneric(mixed $value, string $operator, mixed $operand, bool $strict): void
    {
        $wrapped = $operator === '$in' ? [$operand] : $operand;
        $topLevel = ['v' => [$operator => $wrapped]];
        $nested = ['w.v' => [$operator => $wrapped]];
        $topDocument = ['v' => $value];
        $nestedDocument = ['w' => ['v' => $value]];

        $withoutEvaluator = self::outcome($topLevel, $topDocument, $strict, false);

        $this->assertSame(
            [
                'top-level' => $withoutEvaluator,
                'top-level evaluator' => $withoutEvaluator,
                'nested' => $withoutEvaluator,
                'nested evaluator' => $withoutEvaluator,
            ],
            [
                'top-level' => $withoutEvaluator,
                'top-level evaluator' => self::outcome($topLevel, $topDocument, $strict, true),
                'nested' => self::outcome($nested, $nestedDocument, $strict, false),
                'nested evaluator' => self::outcome($nested, $nestedDocument, $strict, true),
            ],
        );
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $document
     */
    private static function outcome(array $query, array $document, bool $strict, bool $withEvaluator): string
    {
        $qc = new QueryCheck($query);
        $qc->setStrictMode($strict);
        if ($withEvaluator) {
            $qc->setOperandEvaluator(self::identityOperand(...));
        }

        try {
            return $qc->test($document) ? 'true' : 'false';
        } catch (\Throwable $e) {
            // the nested path names the field "w.v", the top-level one "v",
            // in the message and in the location prefix ("w.v.$gt: ...")
            $message = str_replace('variable w.v ', 'variable v ', $e->getMessage());
            return $e::class . ': ' . preg_replace('/^w\.v([.:])/', 'v$1', $message);
        }
    }

    private static function label(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return var_export($value, true);
    }

    // C. Strict-mode rows from the README

    #[TestDox('$ne string vs number // non-strict equal, strict throws like $eq')]
    public function testNeStringVsNumber(): void
    {
        $loose = new QueryCheck(['v' => ['$ne' => 137]]);
        $this->assertFalse($loose->test(['v' => '137']));

        $strict = new QueryCheck(['v' => ['$ne' => 137]]);
        $strict->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$ne: variable v is of type string while operand is of type integer');
        $strict->test(['v' => '137']);
    }

    /**
     * In strict mode, $ne throws exactly when $eq throws; otherwise it is the
     * negation of $eq.
     *
     * @return array<string, array{mixed, mixed}>
     */
    public static function strictNeMirrorsEqProvider(): array
    {
        $values = [137, 137.0, '137', 'abc', null, true, false, [137], ['137'], [], ['a' => 1]];
        $cases = [];
        foreach ($values as $value) {
            foreach ($values as $operand) {
                $cases[self::label($value) . ' vs ' . self::label($operand)] = [$value, $operand];
            }
        }
        return $cases;
    }

    #[TestDox('[STRICT] $ne mirrors $eq (same exception, opposite result)')]
    #[DataProvider('strictNeMirrorsEqProvider')]
    public function testStrictNeMirrorsEq(mixed $value, mixed $operand): void
    {
        $run = static function (string $operator) use ($value, $operand): string {
            $qc = new QueryCheck(['v' => [$operator => $operand]]);
            $qc->setStrictMode(true);
            try {
                return $qc->test(['v' => $value]) ? 'true' : 'false';
            } catch (StrictTypeError $e) {
                return 'throws: ' . str_replace('$ne:', '$eq:', $e->getMessage());
            }
        };

        $eq = $run('$eq');
        $ne = $run('$ne');
        if (str_starts_with($eq, 'throws')) {
            $this->assertSame($eq, $ne);
        } else {
            $this->assertSame($eq === 'true' ? 'false' : 'true', $ne);
        }
    }

    #[TestDox('[STRICT] null vs another type // no match, no exception')]
    public function testStrictNullNeverThrows(): void
    {
        $queries = [
            ['v' => ['$gt' => 5]], ['v' => ['$gte' => 'a']], ['v' => ['$lt' => true]], ['v' => ['$lte' => 5.5]],
            ['$expr' => ['$gt' => ['$v', 5]]], ['$expr' => ['$lt' => ['$v', 'a']]],
        ];
        foreach ($queries as $query) {
            $qc = new QueryCheck($query);
            $qc->setStrictMode(true);
            $this->assertFalse($qc->test(['v' => null]), json_encode($query) . ' on null');
        }

        // null as the operand
        $qc = new QueryCheck(['v' => ['$gte' => null]]);
        $qc->setStrictMode(true);
        $this->assertFalse($qc->test(['v' => 5]));

        // $eq / $ne with null never throw either
        $qc = new QueryCheck(['v' => ['$ne' => 5]]);
        $qc->setStrictMode(true);
        $this->assertTrue($qc->test(['v' => null]));
        $qc = new QueryCheck(['v' => 5]);
        $qc->setStrictMode(true);
        $this->assertFalse($qc->test(['v' => null]));
    }

    #[TestDox('[STRICT] $eq other type mismatch throws StrictTypeError')]
    public function testStrictEqOtherMismatchThrows(): void
    {
        $loose = new QueryCheck(['v' => true]);
        $this->assertFalse($loose->test(['v' => 1]));

        $strict = new QueryCheck(['v' => true]);
        $strict->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $strict->test(['v' => 1]);
    }

    #[TestDox('$in operand that is not a list is a SyntaxError, in both modes')]
    public function testInOperandNotListIsSyntaxError(): void
    {
        foreach ([false, true] as $strict) {
            $qc = new QueryCheck(['v' => ['$in' => 'x']]);
            $qc->setStrictMode($strict);
            try {
                $qc->test(['v' => 'x']);
                $this->fail('no SyntaxError');
            } catch (SyntaxError $e) {
                $this->assertSame('v.$in: $in requires an array, got string', $e->getMessage());
            }
        }
    }

    #[TestDox('[STRICT] $in operand from the evaluator that is not a list throws StrictTypeError')]
    public function testStrictDynamicInOperandNotListThrows(): void
    {
        $qc = new QueryCheck(['v' => ['$in' => ['$var' => 'list']]]);
        $qc->setStrictMode(true);
        $qc->setOperandEvaluator(function (mixed $operand, array $data) use ($qc): mixed {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                return $qc->getVariableValue($operand['$var'], $data);
            }
            return $operand;
        });
        $this->assertTrue($qc->test(['v' => 'x', 'list' => ['x', 'y']]));

        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$in: variable v: operand must be of type array but is of type string');
        $qc->test(['v' => 'x', 'list' => 'x']);
    }

    #[TestDox('[STRICT] ordering a boolean against a number throws StrictTypeError')]
    public function testStrictOrderingBoolVsNumberThrows(): void
    {
        $loose = new QueryCheck(['v' => ['$gt' => 0]]);
        $this->assertFalse($loose->test(['v' => true]));

        $strict = new QueryCheck(['v' => ['$gt' => 0]]);
        $strict->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $strict->test(['v' => true]);
    }

    #[TestDox('[STRICT] ordering a numeric string against a number throws StrictTypeError')]
    public function testStrictOrderingStringVsNumberThrows(): void
    {
        $loose = new QueryCheck(['v' => ['$gt' => 10]]);
        $this->assertTrue($loose->test(['v' => '12']));

        $strict = new QueryCheck(['v' => ['$gt' => 10]]);
        $strict->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $strict->test(['v' => '12']);
    }

    // D. Missing and null fields, on both code paths

    #[TestDox('missing top-level field throws UnknownVariableException')]
    #[DataProvider('codePathProvider')]
    public function testMissingTopLevelFieldThrows(bool $withEvaluator): void
    {
        $qc = $this->make(['missing' => ['$gt' => 1]], $withEvaluator);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'missing' is not defined");
        $qc->test(['v' => 1]);
    }

    #[TestDox('missing top-level field compares as null when throwing is disabled')]
    #[DataProvider('codePathProvider')]
    public function testMissingTopLevelFieldIsFalseWhenThrowDisabled(bool $withEvaluator): void
    {
        $qc = $this->make(['missing' => ['$gt' => 1]], $withEvaluator, throwOnUnknown: false);
        $this->assertFalse($qc->test(['v' => 1]));
    }

    #[TestDox('missing field equals null when setThrowOnUnknownVariable(false)')]
    #[DataProvider('codePathProvider')]
    public function testMissingFieldEqualsNull(bool $withEvaluator): void
    {
        $qc = $this->make(['missing' => null], $withEvaluator, throwOnUnknown: false);
        $this->assertTrue($qc->test(['v' => 1]));
    }

    #[TestDox('missing field $gte null when setThrowOnUnknownVariable(false)')]
    #[DataProvider('codePathProvider')]
    public function testMissingFieldGteNull(bool $withEvaluator): void
    {
        $qc = $this->make(['missing' => ['$gte' => null]], $withEvaluator, throwOnUnknown: false);
        $this->assertTrue($qc->test(['v' => 1]));
    }

    #[TestDox('present null does not match $gt and does not throw')]
    #[DataProvider('codePathProvider')]
    public function testPresentNullGreaterThanIsFalse(bool $withEvaluator): void
    {
        $qc = $this->make(['v' => ['$gt' => 1]], $withEvaluator);
        $this->assertFalse($qc->test(['v' => null]));
    }

    #[TestDox('present null equals null')]
    #[DataProvider('codePathProvider')]
    public function testPresentNullEqualsNull(bool $withEvaluator): void
    {
        $qc = $this->make(['v' => null], $withEvaluator);
        $this->assertTrue($qc->test(['v' => null]));
    }

    #[TestDox('present null matches $lte null')]
    #[DataProvider('codePathProvider')]
    public function testPresentNullLteNull(bool $withEvaluator): void
    {
        $qc = $this->make(['v' => ['$lte' => null]], $withEvaluator);
        $this->assertTrue($qc->test(['v' => null]));
    }

    #[TestDox('missing nested field throws UnknownVariableException')]
    #[DataProvider('codePathProvider')]
    public function testNestedMissingFieldThrows(bool $withEvaluator): void
    {
        $qc = $this->make(['w.missing' => 1], $withEvaluator);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'w.missing' is not defined");
        $qc->test(['w' => ['v' => 1]]);
    }

    #[TestDox('null intermediate equals null')]
    #[DataProvider('codePathProvider')]
    public function testNullIntermediateEqualsNull(bool $withEvaluator): void
    {
        $qc = $this->make(['w.v' => null], $withEvaluator);
        $this->assertTrue($qc->test(['w' => null]));
    }

    #[TestDox('null intermediate does not match $gte')]
    #[DataProvider('codePathProvider')]
    public function testNullIntermediateGteIsFalse(bool $withEvaluator): void
    {
        $qc = $this->make(['w.v' => ['$gte' => 0]], $withEvaluator);
        $this->assertFalse($qc->test(['w' => null]));
    }

    #[TestDox('array index out of range throws UnknownVariableException')]
    #[DataProvider('codePathProvider')]
    public function testArrayIndexOutOfRangeThrows(bool $withEvaluator): void
    {
        $qc = $this->make(['items[5]' => 1], $withEvaluator);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'items[5]' is not defined");
        $qc->test(['items' => [1, 2]]);
    }

    #[TestDox('array index out of range equals null when setThrowOnUnknownVariable(false)')]
    #[DataProvider('codePathProvider')]
    public function testArrayIndexOutOfRangeEqualsNull(bool $withEvaluator): void
    {
        $qc = $this->make(['items[5]' => null], $withEvaluator, throwOnUnknown: false);
        $this->assertTrue($qc->test(['items' => [1, 2]]));
    }

    #[TestDox('$expr missing field throws UnknownVariableException')]
    #[DataProvider('codePathProvider')]
    public function testExprMissingFieldThrows(bool $withEvaluator): void
    {
        $qc = $this->make(['$expr' => ['$eq' => ['$missing', null]]], $withEvaluator);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'missing' is not defined");
        $qc->test(['v' => 1]);
    }

    #[TestDox('$expr missing field equals null when setThrowOnUnknownVariable(false)')]
    #[DataProvider('codePathProvider')]
    public function testExprMissingFieldEqualsNull(bool $withEvaluator): void
    {
        $qc = $this->make(
            ['$expr' => ['$eq' => ['$missing', null]]],
            $withEvaluator,
            throwOnUnknown: false,
        );
        $this->assertTrue($qc->test(['v' => 1]));
    }

    // E. Setters after test() take effect

    #[TestDox('setOperandEvaluator() after test() discards the compiled query')]
    public function testOperandEvaluatorAfterTestTakesEffect(): void
    {
        $qc = new QueryCheck(['v' => ['$var' => 'x']]);
        $document = ['v' => 5, 'x' => 5];
        try {
            // without an evaluator, $var is an unknown operator
            $qc->test($document);
            $this->fail('no SyntaxError');
        } catch (SyntaxError $e) {
            $this->assertSame('v: Unsupported query operator: $var', $e->getMessage());
        }

        $qc->setOperandEvaluator(function (mixed $operand, array $data) use ($qc): mixed {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                return $qc->getVariableValue($operand['$var'], $data);
            }

            return $operand;
        });
        $this->assertTrue($qc->test($document));
    }

    #[TestDox('setStrictMode() after test() discards the compiled query')]
    public function testStrictModeAfterTestTakesEffect(): void
    {
        $qc = new QueryCheck(['v' => ['$gt' => 10]]);
        $qc->setStrictMode(true);
        try {
            $qc->test(['v' => '12']);
            $this->fail('Expected StrictTypeError was not thrown');
        } catch (StrictTypeError) {
        }

        $qc->setStrictMode(false);
        $this->assertTrue($qc->test(['v' => '12']));
    }

    // F. Syntax errors come from the first test(), also from unevaluated branches

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function syntaxErrorProvider(): array
    {
        return [
            'unknown query operator' => [
                ['v' => ['$gt' => 1, 'foo' => 2]],
                'Unsupported query operator: foo',
            ],
            'list as query' => [
                [['v' => 1]],
                'Field names must be strings',
            ],
            'empty key' => [
                ['' => 1],
                'Empty keys are not supported!',
            ],
            '$and not a list' => [
                ['$and' => ['v' => 1]],
                '$and can only operate on arrays of queries',
            ],
            '$or not a list' => [
                ['$or' => ['v' => 1]],
                '$or can only operate on arrays of queries',
            ],
            '$expr requires an object' => [
                ['$expr' => [1, 2]],
                '$expr requires an object expression',
            ],
            '$subtract arity' => [
                ['$expr' => ['$subtract' => [1, 2, 3]]],
                '$subtract requires an array of exactly 2 operands',
            ],
            'unknown aggregation operator in $cond else' => [
                ['$expr' => ['$cond' => ['if' => true, 'then' => true, 'else' => ['$nope' => 1]]]],
                'Unsupported aggregation operator: $nope',
            ],
            'unknown boolean operator in a matching $or branch' => [
                ['$or' => [['v' => 1], ['$nope' => 1]]],
                'Unsupported boolean operator: $nope',
            ],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('syntax error is thrown on the first test()')]
    #[DataProvider('syntaxErrorProvider')]
    public function testSyntaxErrorOnFirstTest(array $query, string $message): void
    {
        $qc = new QueryCheck($query);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        $qc->test(['v' => 1]);
    }

    #[TestDox('syntax error in $expr is thrown on test() when an operand evaluator is set')]
    public function testExprSyntaxErrorWithOperandEvaluator(): void
    {
        $qc = new QueryCheck(['$expr' => ['$eq' => [['$nope' => 1], 1]]]);
        $qc->setOperandEvaluator(self::identityOperand(...));
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unsupported aggregation operator: $nope');
        $qc->test(['v' => 1]);
    }

    // G. $regex

    /**
     * @return array<string, array{array<mixed>, string, bool}>
     */
    public static function regexProvider(): array
    {
        return [
            'pattern containing slash' => [['v' => ['$regex' => 'a/b']], 'xa/by', true],
            '$options i' => [['v' => ['$regex' => '^ab', '$options' => 'i']], 'ABC', true],
            'without $options' => [['v' => ['$regex' => '^ab']], 'ABC', false],
            'escaped slash stays escaped' => [['v' => ['$regex' => 'a\\/b']], 'xa/by', true],
            'escaped backslash before slash' => [['v' => ['$regex' => 'a\\\\/b']], 'xa\\/by', true],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('$regex')]
    #[DataProvider('regexProvider')]
    public function testRegex(array $query, string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->make($query)->test(['v' => $value]));
        $this->assertSame($expected, $this->make($query, withEvaluator: true)->test(['v' => $value]));
    }

    // H. getVariableValue

    #[TestDox('getVariableValue resolves paths, indexes and null intermediates')]
    public function testGetVariableValueResolvesPaths(): void
    {
        $data = ['a' => ['b' => 1], 'items' => [['q' => 5], ['q' => 20]], 'n' => null];
        $qc = new QueryCheck([]);

        $this->assertSame(1, $qc->getVariableValue('a.b', $data));
        $this->assertSame(20, $qc->getVariableValue('items[1].q', $data));
        $this->assertNull($qc->getVariableValue('n', $data));
        $this->assertNull($qc->getVariableValue('n.x', $data));
    }

    #[TestDox('getVariableValue throws UnknownVariableException for a missing field')]
    public function testGetVariableValueMissingThrows(): void
    {
        $qc = new QueryCheck([]);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'missing' is not defined");
        $qc->getVariableValue('missing', ['a' => ['b' => 1]]);
    }

    #[TestDox('getVariableValue returns null for a missing field when throwing is disabled')]
    public function testGetVariableValueMissingReturnsNullWhenThrowDisabled(): void
    {
        $qc = new QueryCheck([]);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertNull($qc->getVariableValue('missing', ['a' => ['b' => 1]]));
    }

    #[TestDox('getVariableValue throws when walking into a scalar')]
    public function testGetVariableValueWalkingIntoScalarThrows(): void
    {
        $qc = new QueryCheck([]);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'a.b.c' is not defined");
        $qc->getVariableValue('a.b.c', ['a' => ['b' => 1]]);
    }

    // I. $not

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function notProvider(): array
    {
        return [
            'inverts a non-match' => [3, true],
            'inverts a match' => [7, false],
            'inverts an unorderable value' => ['abc', true],
        ];
    }

    #[TestDox('$not inverts the inner operator')]
    #[DataProvider('notProvider')]
    public function testNotInvertsInnerOperator(mixed $value, bool $expected): void
    {
        $qc = new QueryCheck(['v' => ['$not' => ['$gt' => 5]]]);
        $this->assertSame($expected, $qc->test(['v' => $value]));
    }

    // J. Objects and lists compare int and float as numbers

    /**
     * @return array<string, array{array<mixed>, mixed}>
     */
    public static function intFloatCompositeProvider(): array
    {
        return [
            'object value' => [['v' => ['a' => 1]], ['a' => 1.0]],
            'list nested in an object' => [['v' => ['a' => [1, 2]]], ['a' => [1.0, 2]]],
            '$in object element' => [['v' => ['$in' => [['x' => 1]]]], ['x' => 1.0]],
            'list' => [['v' => [1, 2]], [1.0, 2.0]],
            '$expr list' => [['$expr' => ['$eq' => ['$v', [1, 2]]]], [1.0, 2]],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('objects and lists compare integers and floats as numbers')]
    #[DataProvider('intFloatCompositeProvider')]
    public function testIntFloatCompositeEquality(array $query, mixed $value): void
    {
        $this->assertTrue((new QueryCheck($query))->test(['v' => $value]));
    }

    // K. Numeric edge values

    /**
     * @return array<string, array{array<mixed>, mixed, bool}>
     */
    public static function numericEdgeProvider(): array
    {
        return [
            '-0.0 equals 0' => [['v' => 0], -0.0, true],
            '-0.0 $ne 0' => [['v' => ['$ne' => 0]], -0.0, false],
            'INF $gt PHP_INT_MAX' => [['v' => ['$gt' => PHP_INT_MAX]], INF, true],
            '-INF $lt -1e308' => [['v' => ['$lt' => -1e308]], -INF, true],
            'INF equals INF' => [['v' => INF], INF, true],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('numeric edge values agree in both modes')]
    #[DataProvider('numericEdgeProvider')]
    public function testNumericEdgeValues(array $query, mixed $value, bool $expected): void
    {
        foreach ([false, true] as $strict) {
            $qc = new QueryCheck($query);
            $qc->setStrictMode($strict);
            $this->assertSame($expected, $qc->test(['v' => $value]), $strict ? 'strict' : 'non-strict');
        }
    }

    // L. validate() and the location in syntax errors

    #[TestDox('validate() accepts a valid query, test() works afterwards')]
    public function testValidateAcceptsValidQuery(): void
    {
        $qc = new QueryCheck([
            'age' => ['$gte' => 18],
            '$or' => [['role' => 'admin'], ['$expr' => ['$gt' => [['$size' => '$tags'], 1]]]],
        ]);
        $qc->validate();
        $qc->validate(); // idempotent

        $this->assertTrue($qc->test(['age' => 30, 'role' => 'user', 'tags' => ['a', 'b']]));
        $this->assertFalse($qc->test(['age' => 12, 'role' => 'admin', 'tags' => []]));
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function syntaxErrorLocationProvider(): array
    {
        return [
            'top-level boolean operator' => [['$nope' => 1], '$nope: Unsupported boolean operator: $nope'],
            'query operator in a field' => [['age' => ['$gt' => 1, 'foo' => 2]], 'age.foo: Unsupported query operator: foo'],
            'dotted field name' => [['trx.amount' => ['$gte' => 1, 'bad' => 1]], 'trx.amount.bad: Unsupported query operator: bad'],
            'second $or branch' => [['$or' => [['v' => 1], ['$nope' => 1]]], '$or[1].$nope: Unsupported boolean operator: $nope'],
            'nested $and / $or / $not' => [
                ['$and' => [['a' => 1], ['$or' => [['b' => 1], ['c' => ['$not' => ['$gt' => 1, 'x' => 2]]]]]]],
                '$and[1].$or[1].c.$not.x: Unsupported query operator: x',
            ],
            '$and not a list' => [['$and' => ['v' => 1]], '$and: $and can only operate on arrays of queries'],
            '$expr not an object' => [['$expr' => [1, 2]], '$expr: $expr requires an object expression'],
            '$expr arity' => [['$expr' => ['$subtract' => [1, 2, 3]]], '$expr.$subtract: $subtract requires an array of exactly 2 operands'],
            '$expr $cond branch' => [
                ['$expr' => ['$cond' => ['if' => true, 'then' => true, 'else' => ['$nope' => 1]]]],
                '$expr.$cond.else.$nope: Unsupported aggregation operator: $nope',
            ],
            '$expr deep operand' => [
                ['$expr' => ['$gt' => [['$add' => [1, ['$size' => ['$nope' => 1]]]], 5]]],
                '$expr.$gt[0].$add[1].$size.$nope: Unsupported aggregation operator: $nope',
            ],
            '$expr $trim input' => [
                ['$expr' => ['$eq' => [['$trim' => ['input' => ['$nope' => 1]]], 'x']]],
                '$expr.$eq[0].$trim.input.$nope: Unsupported aggregation operator: $nope',
            ],
            'list as query' => [[['v' => 1]], '[0]: Field names must be strings, got: 0'],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('validate() throws a SyntaxError with the location')]
    #[DataProvider('syntaxErrorLocationProvider')]
    public function testValidateThrowsWithLocation(array $query, string $message): void
    {
        $qc = new QueryCheck($query);
        try {
            $qc->validate();
            $this->fail('validate() did not throw');
        } catch (SyntaxError $e) {
            $this->assertSame($message, $e->getMessage());
        }

        // test() reports the same error
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        $qc->test(['v' => 1]);
    }

    #[TestDox('the original error, without location, is kept as previous exception')]
    public function testSyntaxErrorKeepsPrevious(): void
    {
        try {
            (new QueryCheck(['$or' => [['v' => 1], ['$nope' => 1]]]))->validate();
            $this->fail('validate() did not throw');
        } catch (SyntaxError $e) {
            $previous = $e->getPrevious();
            $this->assertInstanceOf(SyntaxError::class, $previous);
            $this->assertSame('Unsupported boolean operator: $nope', $previous->getMessage());
        }
    }

    #[TestDox('errors that depend on the document have no location and only come from test()')]
    public function testRuntimeErrorsHaveNoLocation(): void
    {
        $qc = new QueryCheck(['$expr' => ['$gt' => [['$add' => ['$a', 1]], 5]]]);
        $qc->validate();

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$add operands must be numeric');
        try {
            $qc->test(['a' => 'x']);
        } catch (SyntaxError $e) {
            $this->assertSame('$add operands must be numeric', $e->getMessage());
            throw $e;
        }
    }

    #[TestDox('validate() uses the operand evaluator: custom functions are not syntax errors')]
    public function testValidateWithOperandEvaluator(): void
    {
        $query = ['$expr' => ['$gt' => [['$var' => 'price'], 100]]];

        $without = new QueryCheck($query);
        try {
            $without->validate();
            $this->fail('validate() did not throw');
        } catch (SyntaxError $e) {
            $this->assertSame('$expr.$gt[0].$var: Unsupported aggregation operator: $var', $e->getMessage());
        }

        $with = new QueryCheck($query);
        $with->setOperandEvaluator(function (mixed $operand, array $data) use (&$with): mixed {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                return $with->getVariableValue($operand['$var'], $data);
            }
            return $operand;
        });
        $with->validate();
        $this->assertTrue($with->test(['price' => 150]));
    }

    #[TestDox('with an operand evaluator, parts compiled later still report the location')]
    public function testDeferredErrorHasLocation(): void
    {
        $qc = new QueryCheck(['$expr' => ['$eq' => [['$nope' => 1], 1]]]);
        $qc->setOperandEvaluator(self::identityOperand(...));
        $qc->validate(); // deferred: the evaluator might resolve {$nope: 1}

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$expr.$eq[0].$nope: Unsupported aggregation operator: $nope');
        $qc->test(['v' => 1]);
    }

    // M. Compile-time checks

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function compileTimeCheckProvider(): array
    {
        return [
            'typo in operator, top-level field' => [['age' => ['$gtt' => 18]], 'age: Unsupported query operator: $gtt'],
            'typo in operator, nested field' => [['a.b' => ['$gtt' => 18]], 'a.b: Unsupported query operator: $gtt'],
            'field then operator' => [['age' => ['foo' => 1, '$gt' => 18]], 'age: Cannot mix fields and operators in one object: $gt'],
            'lone $options' => [['name' => ['$options' => 'i']], 'name: $options requires $regex'],
            'invalid pattern' => [['name' => ['$regex' => '(']], 'name.$regex: Invalid $regex pattern "(": Compilation failed: missing closing parenthesis at offset 1'],
            'unknown option' => [['name' => ['$regex' => 'J', '$options' => 'e']], 'name.$regex: Unsupported $regex option: e (supported: i, m, s, x, u)'],
            'option is not a string' => [['name' => ['$regex' => 'J', '$options' => 1]], 'name.$regex: $options requires a string, got integer'],
            'pattern is not a string' => [['name' => ['$regex' => 12]], 'name.$regex: $regex requires a string pattern, got integer'],
            'trailing backslash' => [['name' => ['$regex' => 'a\\']], 'name.$regex: $regex pattern ends with an unescaped backslash'],
            'invalid pattern inside $not' => [['name' => ['$not' => ['$regex' => '(']]], 'name.$not.$regex: Invalid $regex pattern "(": Compilation failed: missing closing parenthesis at offset 1'],
            '$in not a list' => [['age' => ['$in' => 30]], 'age.$in: $in requires an array, got integer'],
            '$not with a value' => [['age' => ['$not' => 30]], 'age.$not: $not requires an operator expression like {$not: {$gt: 5}}'],
            '$not with a plain object' => [['age' => ['$not' => ['x' => 1]]], 'age.$not: $not requires an operator expression like {$not: {$gt: 5}}'],
            'empty $and' => [['$and' => []], '$and: $and requires a non-empty array'],
            'empty $or' => [['$or' => []], '$or: $or requires a non-empty array'],
            'ordering against an array' => [['age' => ['$gt' => [18]]], 'age.$gt: $gt cannot compare with an array or object'],
            'ordering against an object' => [['age' => ['$lte' => ['x' => 1]]], 'age.$lte: $lte cannot compare with an array or object'],
            'path with empty segment' => [['a..b' => 1], 'a..b: Invalid field path "a..b": empty segment'],
            'path "."' => [['.' => 1], '.: Invalid field path ".": empty segment'],
            'path ending with a dot' => [['a.' => 1], 'a.: Invalid field path "a.": empty segment'],
            '$expr field reference "$"' => [['$expr' => ['$eq' => ['$', 1]]], '$expr.$eq[0]: Invalid field path "": empty segment'],
            '$expr field reference with empty segment' => [['$expr' => ['$eq' => ['$a..b', 1]]], '$expr.$eq[0]: Invalid field path "a..b": empty segment'],
            '$expr two operators' => [['$expr' => ['$gte' => ['$age', 18], '$lt' => ['$age', 65]]], '$expr: An operator expression must have exactly one key, found: $gte, $lt'],
            '$expr nested two operators' => [['$expr' => ['$eq' => [['$add' => [1], '$size' => '$x'], 1]]], '$expr.$eq[0]: An operator expression must have exactly one key, found: $add, $size'],
            '$expr typo' => [['$expr' => ['gte' => ['$age', 18]]], '$expr: $expr requires an operator expression like {$gt: ["$a", 1]}, got key: gte'],
            '$expr field then operator' => [['$expr' => ['$eq' => [['a' => 1, '$add' => [1]], 1]]], '$expr.$eq[0]: Cannot mix fields and operators in one object: $add'],
            '$divide by literal 0' => [['$expr' => ['$gt' => [['$divide' => ['$a', 0]], 1]]], '$expr.$gt[0].$divide: $divide: division by zero'],
            '$mod by literal 0.0' => [['$expr' => ['$eq' => [['$mod' => ['$a', 0.0]], 1]]], '$expr.$eq[0].$mod: $mod: division by zero'],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('validate() finds mistakes that only the query decides')]
    #[DataProvider('compileTimeCheckProvider')]
    public function testCompileTimeCheck(array $query, string $message): void
    {
        $qc = new QueryCheck($query);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        try {
            $qc->validate();
        } catch (SyntaxError $e) {
            $this->assertSame($message, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, array{array<mixed>, array<mixed>, bool}>
     */
    public static function stillValidProvider(): array
    {
        return [
            'object equality' => [['o' => ['x' => 1, 'y' => 2]], ['o' => ['x' => 1, 'y' => 2]], true],
            'escaped dot in path' => [['a\\.b' => 1], ['a\\.b' => 1], true],
            'array index path' => [['items[1]' => 2], ['items' => [1, 2]], true],
            '$not with $regex' => [['name' => ['$not' => ['$regex' => '^J']]], ['name' => 'Ann'], true],
            '$regex with every option' => [['name' => ['$regex' => '^j o', '$options' => 'imsxu']], ['name' => 'John'], true],
            '$regex with an escaped slash' => [['name' => ['$regex' => 'a\\/b']], ['name' => 'a/b'], true],
            '$expr literal object as value' => [['$expr' => ['$eq' => ['$o', ['x' => 1]]]], ['o' => ['x' => 1]], true],
            '$expr non-boolean result counts as true' => [['$expr' => ['$add' => ['$a', 1]]], ['a' => 5], true],
            '$cond with a null branch' => [['$expr' => ['$eq' => [['$cond' => ['if' => false, 'then' => 1, 'else' => null]], null]]], ['v' => 1], true],
            '$divide by a field' => [['$expr' => ['$gt' => [['$divide' => ['$a', '$b']], 1]]], ['a' => 4, 'b' => 2], true],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $document
     */
    #[TestDox('valid queries pass validate()')]
    #[DataProvider('stillValidProvider')]
    public function testStillValid(array $query, array $document, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $qc->validate();
        $this->assertSame($expected, $qc->test($document));
    }

    #[TestDox('with an operand evaluator, unknown $ keys in a field condition are custom functions')]
    public function testUnknownOperatorAllowedWithEvaluator(): void
    {
        $qc = new QueryCheck(['v' => ['$var' => 'x'], 'w' => ['a' => 1, '$var' => 'y']]);
        $qc->setOperandEvaluator(function (mixed $operand, array $data) use (&$qc): mixed {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                return $qc->getVariableValue($operand['$var'], $data);
            }
            return $operand;
        });
        $qc->validate();
        $this->assertTrue($qc->test(['v' => 5, 'x' => 5, 'w' => 7, 'y' => 7]));
    }

    #[TestDox('with an operand evaluator, a literal operand is checked when the evaluator returns it unchanged')]
    public function testLiteralOperandCheckedWithEvaluator(): void
    {
        $qc = new QueryCheck(['$or' => [['a' => 1], ['age' => ['$gt' => [18]]]]]);
        $qc->setOperandEvaluator(self::identityOperand(...));
        $qc->validate(); // deferred: the evaluator might resolve [18]
        $this->assertTrue($qc->test(['a' => 1, 'age' => 30])); // second branch not reached

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$or[1].age.$gt: $gt cannot compare with an array or object');
        $qc->test(['a' => 2, 'age' => 30]);
    }

    #[TestDox('with an operand evaluator, an invalid $regex pattern throws at test() with the location')]
    public function testDynamicRegexPatternChecked(): void
    {
        $qc = new QueryCheck(['name' => ['$regex' => ['$var' => 'pattern']]]);
        $qc->setOperandEvaluator(function (mixed $operand, array $data) use (&$qc): mixed {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                return $qc->getVariableValue($operand['$var'], $data);
            }
            return $operand;
        });
        $this->assertTrue($qc->test(['name' => 'John', 'pattern' => '^J']));

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('name.$regex: Invalid $regex pattern "(": Compilation failed');
        $qc->test(['name' => 'John', 'pattern' => '(']);
    }

    #[TestDox('a $regex that fails while matching throws RegexError, also under $not')]
    public function testRegexMatchingFailureThrows(): void
    {
        $cases = [
            'backtrack limit' => [['v' => ['$regex' => '^(a+)+$']], str_repeat('a', 40) . 'b', 'Backtrack limit exhausted'],
            'backtrack limit under $not' => [['v' => ['$not' => ['$regex' => '^(a+)+$']]], str_repeat('a', 40) . 'b', 'Backtrack limit exhausted'],
            'invalid UTF-8 under $not' => [['v' => ['$not' => ['$regex' => '.', '$options' => 'u']]], "\xff", 'Malformed UTF-8'],
        ];
        foreach ($cases as $label => [$query, $value, $reason]) {
            try {
                (new QueryCheck($query))->test(['v' => $value]);
                $this->fail("$label: no RegexError");
            } catch (\Maurice2k\QueryCheck\Exception\RegexError $e) {
                $this->assertStringStartsWith('$regex: matching variable v failed: ', $e->getMessage(), $label);
                $this->assertStringContainsString($reason, $e->getMessage(), $label);
            }
        }
    }

    /**
     * @return array<string, array{mixed, bool, string|null}>
     */
    public static function regexValueProvider(): array
    {
        // value, non-strict result, strict exception message (null: same result as non-strict)
        return [
            'string' => ['12a', true, null],
            'integer' => [12, true, '$regex: variable v is of type integer while a string is expected'],
            'float' => [12.5, true, '$regex: variable v is of type double while a string is expected'],
            'null' => [null, false, null],
            'boolean' => [true, false, '$regex: variable v is of type boolean while a string is expected'],
            'array' => [['12'], false, '$regex: variable v is of type array while a string is expected'],
            'NaN' => [NAN, false, '$regex: variable v is of type double while a string is expected'],
        ];
    }

    #[TestDox('$regex on non-strings: numbers as text without strict mode, otherwise no match; strict mode throws')]
    #[DataProvider('regexValueProvider')]
    public function testRegexValueTypes(mixed $value, bool $expected, ?string $strictMessage): void
    {
        $query = ['v' => ['$regex' => '^1']];
        $this->assertSame($expected, (new QueryCheck($query))->test(['v' => $value]));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        if ($strictMessage === null) {
            $this->assertSame($expected, $strict->test(['v' => $value]));
            return;
        }
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage($strictMessage);
        $strict->test(['v' => $value]);
    }

    #[TestDox('a setter after validate() discards the compiled query')]
    public function testSetterAfterValidateRecompiles(): void
    {
        $qc = new QueryCheck(['v' => ['$gt' => 10]]);
        $qc->validate();
        $this->assertTrue($qc->test(['v' => '12']));

        $qc->setStrictMode(true);
        $qc->validate();
        $this->expectException(StrictTypeError::class);
        $qc->test(['v' => '12']);
    }
}
