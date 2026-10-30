<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Tests;

use Maurice2k\QueryCheck\QueryCheck;
use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\SyntaxError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class QueryCheckTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $vars;

    protected function setUp(): void
    {
        $this->vars = [
            'now' => [
                'isWeekday' => true,
                'isHoliday' => false,
                'isoDate' => '2020-05-21',
                'isoTime' => '13:59:48',
                'isoDateTime' => '2020-05-21T13:59:48',
            ],
            'myString' => 'this is a string',
            'myStringFlavor' => 'strawberry',
            'myStringPrime' => 'prime number 137',
            'myInt' => 137,
            'myIntAsString' => '137',
            'myFloat' => 137.12345,
            'myBoolTrue' => true,
            'myBoolFalse' => false,
            'myNull' => null,
            'myArrayOfInts' => [10, 20, 30, 40, 50],
            'myArrayOfStrings' => ['vanilla', 'strawberry', 'chocolate'],
            'myArrayOfObjects' => [['x' => 10, 'y' => 11], ['x' => 20, 'y' => 21], ['x' => 40, 'y' => 41]],
            'mySimpleObject' => ['x' => 30, 'y' => 31],
            'myLookup' => [
                0 => 'zero',
                '1' => 'one',
                'user' => 'maurice',
            ],
            'lookupKey' => 'user',
            'myObject' => [
                'userName' => 'maurice',
                'firstName' => 'First',
                'lastName' => 'Last',
            ],
            'myEmptyArray' => [],
        ];
    }

    // STANDARD MODE TESTS

    #[TestDox('$eq short syntax // string == string')]
    public function testEqShortSyntaxStringEqualsString(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq with null value // null == null')]
    public function testEqWithNullValueNullEqualsNull(): void
    {
        $qc = new QueryCheck(['myNull' => null]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq with null value // string != null')]
    public function testEqWithNullValueStringNotEqualsNull(): void
    {
        $qc = new QueryCheck(['myString' => null]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax sub-variable // string == string')]
    public function testEqShortSyntaxSubVariableStringEqualsString(): void
    {
        $qc = new QueryCheck(['myObject.userName' => 'maurice']);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('setThrowOnUnknownVariable(false) does not throw and missing compares as null')]
    public function testThrowOnUnknownVariableCanBeDisabled(): void
    {
        $qc = new QueryCheck(['myUndefined' => 'some string']);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertFalse($qc->test($this->vars));

        $qc = new QueryCheck(['myUndefined' => null]);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('setThrowOnUnknownVariable(true) throws UnknownVariableException')]
    public function testThrowOnUnknownVariableCanBeEnabled(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => 'some string']);
        $qc->setThrowOnUnknownVariable(true);
        $this->expectException(UnknownVariableException::class);
        $this->expectExceptionMessage("Variable 'myObject.myUndefined' is not defined");
        $qc->test($this->vars);
    }

    #[TestDox('$expr missing field does not throw when setThrowOnUnknownVariable(false)')]
    public function testExprMissingFieldDoesNotThrowWhenDisabled(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => ['$missing', null],
            ],
        ]);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test(['other' => 1]));
    }

    #[TestDox('$eq short syntax non-existant sub-variable // undefined throws exception')]
    public function testEqShortSyntaxNonExistantSubVariableUndefinedThrowsException(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => 'some string']);
        $qc->setStrictMode(false);
        $this->expectException(UnknownVariableException::class);
        $qc->test($this->vars);
    }

    #[TestDox('$eq short syntax non-existant sub-variable // undefined == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxNonExistantSubVariableUndefinedIsNullWhenNotThrowing(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // int[137] == int[137]')]
    public function testEqShortSyntaxIntEqualsInt(): void
    {
        $qc = new QueryCheck(['myInt' => 137]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // int[137] == string["137"]')]
    public function testEqShortSyntaxIntEqualsString(): void
    {
        $qc = new QueryCheck(['myInt' => '137']);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // string["137"] == int[137]')]
    public function testEqShortSyntaxStringEqualsInt(): void
    {
        $qc = new QueryCheck(['myIntAsString' => 137]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // float[137.12345] == float[137.12345]')]
    public function testEqShortSyntaxFloatEqualsFloat(): void
    {
        $qc = new QueryCheck(['myFloat' => 137.12345]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // float[137.12345] == string["137.12345"]')]
    public function testEqShortSyntaxFloatEqualsString(): void
    {
        $qc = new QueryCheck(['myFloat' => '137.12345']);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // object == object')]
    public function testEqShortSyntaxObjectEqualsObject(): void
    {
        $qc = new QueryCheck(['mySimpleObject' => ['x' => 30, 'y' => 31]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // string != null')]
    public function testEqShortSyntaxStringNotEqualsNull(): void
    {
        $qc = new QueryCheck(['myString' => null]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // null != ""')]
    public function testEqShortSyntaxNullNotEqualsEmptyString(): void
    {
        $qc = new QueryCheck(['myNull' => '']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // undefined throws exception')]
    public function testEqShortSyntaxUndefinedThrowsException(): void
    {
        $qc = new QueryCheck(['myUndefined' => '']);
        $qc->setStrictMode(false);
        $this->expectException(UnknownVariableException::class);
        $qc->test($this->vars);
    }

    #[TestDox('$eq short syntax // undefined == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxUndefinedIsNullWhenNotThrowing(): void
    {
        $qc = new QueryCheck(['myUndefined' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // array[1] == object')]
    public function testEqShortSyntaxArrayIndexEqualsObject(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[1]' => ['x' => 20, 'y' => 21]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // array[1].x == int')]
    public function testEqShortSyntaxArrayIndexPropertyEqualsInt(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[1].x' => 20]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // array[20] == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxArrayOutOfBoundsEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[20]' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // array[20].a.b.c.d == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxArrayDeepNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[20].a.b.c.d' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // myNull.but.this.does.not.exist == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxNullPropertyNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myNull.but.this.does.not.exist' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // myInt.but.this.does.not.exist == null with setThrowOnUnknownVariable(false)')]
    public function testEqShortSyntaxIntPropertyNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myInt.but.this.does.not.exist' => null]);
        $qc->setStrictMode(false);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // string != different string')]
    public function testEqFullSyntaxStringNotEqualsDifferentString(): void
    {
        $qc = new QueryCheck(['myString' => ['$eq' => 'this is a different string']]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // int != null')]
    public function testEqFullSyntaxIntNotEqualsNull(): void
    {
        $qc = new QueryCheck(['myInt' => ['$eq' => null]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // array == array')]
    public function testEqFullSyntaxArrayEqualsArray(): void
    {
        $qc = new QueryCheck(['myArrayOfInts' => ['$eq' => [10, 20, 30, 40, 50]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // array != other array')]
    public function testEqFullSyntaxArrayNotEqualsOtherArray(): void
    {
        $qc = new QueryCheck(['myArrayOfInts' => ['$eq' => [17]]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // array of int == int (included in array)')]
    public function testEqFullSyntaxArrayOfIntEqualsIntIncludedInArray(): void
    {
        $qc = new QueryCheck(['myArrayOfInts' => ['$eq' => 30]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // array of objects == object (included in array)')]
    public function testEqFullSyntaxArrayOfObjectsEqualsObjectIncludedInArray(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects' => ['$eq' => ['x' => 10, 'y' => 11]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq full syntax // array of strings == string (included in array)')]
    public function testEqFullSyntaxArrayOfStringsEqualsStringIncludedInArray(): void
    {
        $qc = new QueryCheck(['myArrayOfStrings' => ['$eq' => 'vanilla']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$in full syntax // int $in array')]
    public function testInFullSyntaxIntInArray(): void
    {
        $qc = new QueryCheck(['myInt' => ['$in' => ['A', 'B', 137]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$in full syntax // int $in array (but int not included)')]
    public function testInFullSyntaxIntNotInArray(): void
    {
        $qc = new QueryCheck(['myInt' => ['$in' => ['A', 'B']]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$not full syntax // $not { myInt == 138 }')]
    public function testNotFullSyntaxNotIntEquals138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$not' => ['$eq' => 138]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$not full syntax // $not { int $in array (but int not included) }')]
    public function testNotFullSyntaxNotIntInArray(): void
    {
        $qc = new QueryCheck(['myInt' => ['$not' => ['$in' => ['A', 'B']]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$ne full syntax // int != different int')]
    public function testNeFullSyntaxIntNotEqualsDifferentInt(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => 138]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$ne full syntax // string != different string')]
    public function testNeFullSyntaxStringNotEqualsDifferentString(): void
    {
        $qc = new QueryCheck(['myString' => ['$ne' => 'hello world']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$ne full syntax // int[137] != string["138"]')]
    public function testNeFullSyntaxIntNotEqualsString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '138']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$ne full syntax // int[137] != string["137"]')]
    public function testNeFullSyntaxIntNotEqualsString137(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '137']]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$gt full syntax // int[137] > int[136]')]
    public function testGtFullSyntaxIntGreaterThan136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => 136]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$gt full syntax // int[137] > string["136"]')]
    public function testGtFullSyntaxIntGreaterThanString136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => '136']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$gt full syntax // int[137] > int[138]')]
    public function testGtFullSyntaxIntNotGreaterThan138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => 138]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$gt full syntax // int[137] > string["138"]')]
    public function testGtFullSyntaxIntNotGreaterThanString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => '138']]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$lt full syntax // int[137] < int[138]')]
    public function testLtFullSyntaxIntLessThan138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => 138]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$lt full syntax // int[137] < string["138"]')]
    public function testLtFullSyntaxIntLessThanString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => '138']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$lt full syntax // int[137] < int[136]')]
    public function testLtFullSyntaxIntNotLessThan136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => 136]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$lt full syntax // int[137] < string["136"]')]
    public function testLtFullSyntaxIntNotLessThanString136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => '136']]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$regex full syntax // string matches other string')]
    public function testRegexFullSyntaxStringMatches(): void
    {
        $qc = new QueryCheck(['myString' => ['$regex' => '^this is a']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$regex full syntax // string does not match other STRING')]
    public function testRegexFullSyntaxStringDoesNotMatchUppercase(): void
    {
        $qc = new QueryCheck(['myString' => ['$regex' => '^THIS IS A']]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$regex full syntax // string matches other STRING with option case-insensitive')]
    public function testRegexFullSyntaxStringMatchesCaseInsensitive(): void
    {
        $qc = new QueryCheck(['myString' => ['$regex' => '^THIS IS A', '$options' => 'i']]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('test with null input data')]
    public function testWithNullInputData(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test(null));
    }

    #[TestDox('test with non-hash data (array)')]
    public function testWithNonHashDataArray(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test([]));
    }

    #[TestDox('test with non-hash data (string)')]
    public function testWithNonHashDataString(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test('not an object'));
    }

    #[TestDox('test with non-hash data (number)')]
    public function testWithNonHashDataNumber(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test(42));
    }

    #[TestDox('test with non-hash data (boolean)')]
    public function testWithNonHashDataBoolean(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test(true));
    }

    #[TestDox('ANDed short syntax')]
    public function testAndedShortSyntax(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string', 'myInt' => 137]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('ANDed full syntax')]
    public function testAndedFullSyntax(): void
    {
        $qc = new QueryCheck(['$and' => [['myString' => 'this is a string'], ['myInt' => 137]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('ORed full syntax // true')]
    public function testOredFullSyntaxTrue(): void
    {
        $qc = new QueryCheck(['$or' => [['myString' => 'this is a different string'], ['myInt' => 137]]]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('ORed full syntax // false')]
    public function testOredFullSyntaxFalse(): void
    {
        $qc = new QueryCheck(['$or' => [['myString' => 'this is a different string'], ['myInt' => 138]]]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('AND with sub-OR full syntax')]
    public function testAndWithSubOrFullSyntax(): void
    {
        $qc = new QueryCheck([
            '$and' => [
                [
                    '$or' => [
                        ['myString' => 'this is a different string'],
                        ['myInt' => 137],
                    ],
                ],
                [
                    '$or' => [
                        ['myBoolTrue' => true],
                        ['myInt' => 138],
                    ],
                ],
            ],
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('implicit AND with $in, $not, $gt and $lt (opening hours 1)')]
    public function testImplicitAndOpeningHours1(): void
    {
        $qc = new QueryCheck([
            'now.isoDate' => ['$in' => ['2019-12-25', '2019-12-26', '2019-12-31', '2020-01-01']],
            'now.isoTime' => ['$not' => ['$gt' => '10:00', '$lt' => '18:00']],
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('implicit AND with $in, $not, $gt and $lt (opening hours 2)')]
    public function testImplicitAndOpeningHours2(): void
    {
        $qc = new QueryCheck([
            'now.isoDate' => ['$in' => ['2019-12-25', '2019-12-26', '2019-12-31', '2020-01-01', '2020-05-21']],
            'now.isoTime' => ['$not' => ['$gt' => '10:00', '$lt' => '12:00']],
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    // STRICT MODE TESTS

    #[TestDox('[STRICT] $eq short syntax non-existant sub-variable // undefined throws exception')]
    public function testStrictEqShortSyntaxNonExistantSubVariableUndefinedThrowsException(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => 'some string']);
        $qc->setStrictMode(true);
        $this->expectException(UnknownVariableException::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $eq short syntax non-existant sub-variable // undefined == null with setThrowOnUnknownVariable(false)')]
    public function testStrictEqShortSyntaxNonExistantSubVariableUndefinedIsNullWhenNotThrowing(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => null]);
        $qc->setStrictMode(true);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('[STRICT] $eq short syntax // int[137] == string["137"]')]
    public function testStrictEqShortSyntaxIntEqualsString(): void
    {
        $qc = new QueryCheck(['myInt' => '137']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $eq short syntax // string["137"] == int[137]')]
    public function testStrictEqShortSyntaxStringEqualsInt(): void
    {
        $qc = new QueryCheck(['myIntAsString' => 137]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $eq short syntax // float[137.12345] == string["137.12345"]')]
    public function testStrictEqShortSyntaxFloatEqualsString(): void
    {
        $qc = new QueryCheck(['myFloat' => '137.12345']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $eq short syntax // undefined throws exception')]
    public function testStrictEqShortSyntaxUndefinedThrowsException(): void
    {
        $qc = new QueryCheck(['myUndefined' => '']);
        $qc->setStrictMode(true);
        $this->expectException(UnknownVariableException::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $ne full syntax // int[137] != string["138"] throws, like $eq')]
    public function testStrictNeFullSyntaxIntNotEqualsString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '138']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$ne: variable myInt is of type integer while operand is of type string');
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $ne full syntax // int[137] != string["137"] throws, like $eq')]
    public function testStrictNeFullSyntaxIntNotEqualsString137(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '137']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$ne: variable myInt is of type integer while operand is of type string');
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $ne full syntax // int[137] != int[138] still works')]
    public function testStrictNeFullSyntaxIntNotEqualsInt138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => 138]]);
        $qc->setStrictMode(true);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('[STRICT] $gt full syntax // int[137] > string["136"]')]
    public function testStrictGtFullSyntaxIntGreaterThanString136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => '136']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $gt full syntax // int[137] > string["138"]')]
    public function testStrictGtFullSyntaxIntNotGreaterThanString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => '138']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $lt full syntax // int[137] < string["138"]')]
    public function testStrictLtFullSyntaxIntLessThanString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => '138']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] $lt full syntax // int[137] < string["136"]')]
    public function testStrictLtFullSyntaxIntNotLessThanString136(): void
    {
        $qc = new QueryCheck(['myInt' => ['$lt' => '136']]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('[STRICT] test with null input data')]
    public function testStrictWithNullInputData(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test(null);
    }

    #[TestDox('[STRICT] test with non-hash data (array)')]
    public function testStrictWithNonHashDataArray(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test([]);
    }

    #[TestDox('[STRICT] test with non-hash data (string)')]
    public function testStrictWithNonHashDataString(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test('not an object');
    }

    #[TestDox('[STRICT] test with non-hash data (number)')]
    public function testStrictWithNonHashDataNumber(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test(42);
    }

    #[TestDox('[STRICT] test with non-hash data (boolean)')]
    public function testStrictWithNonHashDataBoolean(): void
    {
        $qc = new QueryCheck(['myString' => 'this is a string']);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test(true);
    }

    // OPERAND EVALUATOR TESTS

    #[TestDox('$time function returns given date')]
    public function testTimeFunctionReturnsGivenDate(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$time' => ['date' => '2020-05-21T13:59:48']]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$time function returns given date using different input format')]
    public function testTimeFunctionReturnsGivenDateUsingInputFormat(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$time' => ['date' => '21.05.2020, 13:59:48', 'inputFormat' => 'dd.MM.yyyy, HH:mm:ss']]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$time function returns modified date (-2 days and -3 hours)')]
    public function testTimeFunctionReturnsModifiedDate(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$time' => ['date' => '2020-05-23T16:59:48', 'sub' => ['days' => 2, 'hours' => 3]]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$time function cascading (+10 days and -10 days)')]
    public function testTimeFunctionCascading(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$time' => ['date' => ['$time' => ['date' => '2020-05-21T13:59:48', 'sub' => ['days' => 10]]], 'add' => ['days' => 10]]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$time function cascading (+10 days and -10 days) combined with $var function')]
    public function testTimeFunctionCascadingWithVar(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$time' => ['date' => ['$time' => ['date' => ['$var' => ['name' => 'now.isoDateTime']], 'sub' => ['days' => 10]]], 'add' => ['days' => 10]]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$concat function with string and $var (all of type string)')]
    public function testConcatFunctionWithStrings(): void
    {
        $qc = new QueryCheck(['now.isoDateTime' => ['$concat' => [['$var' => ['name' => 'now.isoDate']], 'T', ['$var' => ['name' => 'now.isoTime']]]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$concat function with string and $var (with string casting)')]
    public function testConcatFunctionWithStringCasting(): void
    {
        $qc = new QueryCheck(['myStringPrime' => ['$concat' => ['prime', ' ', 'number', ' ', ['$var' => ['name' => 'myInt']]]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$lookup function with static map/key (key exists)')]
    public function testLookupFunctionWithStaticMapKey(): void
    {
        $qc = new QueryCheck(['myObject.userName' => ['$lookup' => ['map' => ['this' => 'is', 'a' => 'lookup', 'user' => 'maurice'], 'key' => 'user', 'default' => null]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$lookup function with variable map/key (key exists)')]
    public function testLookupFunctionWithVariableMapKey(): void
    {
        $qc = new QueryCheck(['myObject.userName' => ['$lookup' => ['map' => ['$var' => 'myLookup'], 'key' => ['$var' => 'lookupKey'], 'default' => null]]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$lookup function with variable map and static key (where key does not exist)')]
    public function testLookupFunctionKeyDoesNotExist(): void
    {
        $qc = new QueryCheck(['myObject.userName' => ['$lookup' => ['map' => ['$var' => 'myLookup'], 'key' => 'non-existant']]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$lookup function with variable map and static key (where key does not exist but default matches)')]
    public function testLookupFunctionKeyDoesNotExistButDefaultMatches(): void
    {
        $qc = new QueryCheck(['myObject.userName' => ['$lookup' => ['map' => ['$var' => 'myLookup'], 'key' => 'non-existant', 'default' => 'maurice']]]);
        $qc->setOperandEvaluator($this->createOpEvaluator($qc));
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    private function createOpEvaluator(QueryCheck $qc): \Closure
    {
        $opEvaluator = static fn($operand, $data) => $operand;

        $concatFunc = function ($params, $data) use (&$opEvaluator) {
            if (!is_array($params)) {
                return '';
            }

            $str = '';
            foreach ($params as $item) {
                $str .= (string)$opEvaluator($item, $data);
            }
            return $str;
        };

        $lookupFunc = function ($params, $data) use (&$opEvaluator) {
            $default = $params['default'] ?? null;

            if (!isset($params['key'])) {
                return $default;
            }

            $key = $opEvaluator($params['key'], $data);

            if (!isset($params['map']) || !is_array($params['map'])) {
                return $default;
            }

            $map = $opEvaluator($params['map'], $data);

            return $map[$key] ?? $default;
        };

        $varFunc = function ($params, $data) use ($qc) {
            if (is_string($params)) {
                $params = ['name' => $params];
            } elseif (!isset($params['name'])) {
                return null;
            }

            return $qc->getVariableValue($params['name'], $data);
        };

        $timeFunc = function ($params, $data) use (&$opEvaluator) {
            if (is_array($params) && array_key_exists('date', $params)) {
                $params['date'] = $opEvaluator($params['date'], $data);
            }

            $date = $params['date'] ?? null;
            if (is_string($date)) {
                $inputFormat = $params['inputFormat'] ?? '';
                if (is_string($inputFormat) && $inputFormat !== '') {
                    $dateLocal = \DateTimeImmutable::createFromFormat(
                        $this->convertDateFnsFormat($inputFormat),
                        $date
                    );
                    if ($dateLocal === false) {
                        throw new \RuntimeException('Unable to parse date: ' . $date);
                    }
                } else {
                    $dateLocal = new \DateTimeImmutable($date);
                }
            } else {
                $dateLocal = new \DateTimeImmutable();
            }

            if (isset($params['sub']) && is_array($params['sub'])) {
                $dateLocal = $dateLocal->sub($this->dateIntervalFromParts($params['sub']));
            } elseif (isset($params['add']) && is_array($params['add'])) {
                $dateLocal = $dateLocal->add($this->dateIntervalFromParts($params['add']));
            }

            $format = $params['format'] ?? "yyyy-MM-dd'T'HH:mm:ss";
            if (!is_string($format)) {
                $format = "yyyy-MM-dd'T'HH:mm:ss";
            }

            return $dateLocal->format($this->convertDateFnsFormat($format));
        };

        $opEvalFuncs = [
            '$time' => $timeFunc,
            '$concat' => $concatFunc,
            '$lookup' => $lookupFunc,
            '$var' => $varFunc,
        ];

        $opEvaluator = function ($operand, $data) use ($opEvalFuncs) {
            $isObject = is_array($operand) && !array_is_list($operand);
            if (!$isObject) {
                return $operand;
            }

            $firstKey = array_keys($operand)[0];
            if (isset($opEvalFuncs[$firstKey])) {
                return $opEvalFuncs[$firstKey]($operand[$firstKey], $data);
            }

            return $operand;
        };

        return $opEvaluator;
    }

    /**
     * Convert the date-fns tokens used by the JavaScript tests into PHP date() tokens.
     */
    private function convertDateFnsFormat(string $format): string
    {
        $result = '';
        $length = strlen($format);
        $quoted = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === "'") {
                if ($quoted && ($i + 1) < $length && $format[$i + 1] === "'") {
                    $result .= "\\'";
                    $i++;
                    continue;
                }
                $quoted = !$quoted;
                continue;
            }

            if ($quoted) {
                $result .= '\\' . $char;
                continue;
            }

            $rest = substr($format, $i);
            if (str_starts_with($rest, 'yyyy')) {
                $result .= 'Y';
                $i += 3;
                continue;
            }
            if (str_starts_with($rest, 'MM')) {
                $result .= 'm';
                $i += 1;
                continue;
            }
            if (str_starts_with($rest, 'dd')) {
                $result .= 'd';
                $i += 1;
                continue;
            }
            if (str_starts_with($rest, 'HH')) {
                $result .= 'H';
                $i += 1;
                continue;
            }
            if (str_starts_with($rest, 'mm')) {
                $result .= 'i';
                $i += 1;
                continue;
            }
            if (str_starts_with($rest, 'ss')) {
                $result .= 's';
                $i += 1;
                continue;
            }

            $result .= ctype_alpha($char) ? '\\' . $char : $char;
        }

        return $result;
    }

    /**
     * @param array<string, int> $parts
     */
    private function dateIntervalFromParts(array $parts): \DateInterval
    {
        $days = (int)($parts['days'] ?? 0) + 7 * (int)($parts['weeks'] ?? 0);
        $interval = new \DateInterval(sprintf(
            'P%dDT%dH%dM%dS',
            $days,
            (int)($parts['hours'] ?? 0),
            (int)($parts['minutes'] ?? 0),
            (int)($parts['seconds'] ?? 0)
        ));
        $interval->y = (int)($parts['years'] ?? 0);
        $interval->m = (int)($parts['months'] ?? 0);

        return $interval;
    }

    // $EXPR TESTS

    #[TestDox('$expr with $gt and $add // (field1 + field2) > 10')]
    public function testExprWithGtAndAdd(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$gt' => [
                    ['$add' => ['$myInt', '$myFloat']],
                    200
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr compare two fields // spent > budget')]
    public function testExprCompareTwoFields(): void
    {
        $vars = ['spent' => 450, 'budget' => 400];
        $qc = new QueryCheck([
            '$expr' => [
                '$gt' => ['$spent', '$budget']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $subtract // (price - discount) < 100')]
    public function testExprWithSubtract(): void
    {
        $vars = ['price' => 100, 'discount' => 20];
        $qc = new QueryCheck([
            '$expr' => [
                '$lt' => [
                    ['$subtract' => ['$price', '$discount']],
                    90
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $multiply // (field1 * field2) >= 35')]
    public function testExprWithMultiply(): void
    {
        $vars = ['field1' => 5, 'field2' => 7];
        $qc = new QueryCheck([
            '$expr' => [
                '$gte' => [
                    ['$multiply' => ['$field1', '$field2']],
                    35
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $divide // (field1 / field2) == 2')]
    public function testExprWithDivide(): void
    {
        $vars = ['field1' => 10, 'field2' => 5];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$divide' => ['$field1', '$field2']],
                    2
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $mod // (field1 % field2) == 2')]
    public function testExprWithMod(): void
    {
        $vars = ['field1' => 17, 'field2' => 5];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$mod' => ['$field1', '$field2']],
                    2
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $cond // conditional discount calculation')]
    public function testExprWithCond(): void
    {
        $vars = ['qty' => 150, 'price' => 100];
        $qc = new QueryCheck([
            '$expr' => [
                '$lt' => [
                    [
                        '$cond' => [
                            'if' => ['$gte' => ['$qty', 100]],
                            'then' => ['$multiply' => ['$price', 0.5]],
                            'else' => ['$multiply' => ['$price', 0.75]]
                        ]
                    ],
                    60
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with complex nested expression')]
    public function testExprComplexNested(): void
    {
        $vars = ['field1' => 5, 'field2' => 7];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$add' => [
                        ['$multiply' => ['$field1', 2]],
                        ['$subtract' => ['$field2', 3]]
                    ]],
                    14
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $ne // field1 != field2')]
    public function testExprWithNe(): void
    {
        $vars = ['field1' => 5, 'field2' => 7];
        $qc = new QueryCheck([
            '$expr' => [
                '$ne' => ['$field1', '$field2']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $lte // field1 <= field2')]
    public function testExprWithLte(): void
    {
        $vars = ['field1' => 5, 'field2' => 5];
        $qc = new QueryCheck([
            '$expr' => [
                '$lte' => ['$field1', '$field2']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr returns false when condition not met')]
    public function testExprReturnsFalse(): void
    {
        $vars = ['field1' => 5, 'field2' => 7];
        $qc = new QueryCheck([
            '$expr' => [
                '$gt' => [
                    ['$add' => ['$field1', '$field2']],
                    20
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars));
    }

    #[TestDox('$expr with operandEvaluator for custom $var resolution')]
    public function testExprWithOperandEvaluator(): void
    {
        $vars = ['price' => 100, 'multiplier' => 2];

        // Use operandEvaluator to resolve custom $var references
        $qc = new QueryCheck([
            '$expr' => [
                '$gt' => [
                    ['$multiply' => [['$var' => 'price'], ['$var' => 'multiplier']]],
                    150
                ]
            ]
        ]);

        $varFunc = function ($params, $data) use ($qc) {
            if (is_array($params) && isset($params['$var'])) {
                $varName = $params['$var'];
                return $qc->getVariableValue($varName, $data);
            }
            return $params;
        };

        $qc->setOperandEvaluator($varFunc);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // 100 * 2 = 200 > 150
    }

    #[TestDox('$expr with $add treats field names without $ as literal strings')]
    public function testExprFieldWithoutDollarSignTreatedAsLiteral(): void
    {
        $vars = ['field1' => 10, 'field2' => 20];

        // Without $ prefix, "field1" is a literal string, not a field reference
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$add' => ['field1', 5]],  // "field1" is literal string, not $field1
                    'field15'  // String concatenation doesn't work with $add, but this tests literal handling
                ]
            ]
        ]);

        $qc->setStrictMode(false);

        // This should throw an error because "field1" (string) is not numeric
        $this->expectException(\Maurice2k\QueryCheck\Exception\SyntaxError::class);
        $this->expectExceptionMessage('$add operands must be numeric');
        $qc->test($vars);
    }

    #[TestDox('$expr with $add using correct $field syntax works')]
    public function testExprFieldWithDollarSignWorks(): void
    {
        $vars = ['field1' => 10, 'field2' => 20];

        // With $ prefix, $field1 is a field reference
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$add' => ['$field1', '$field2']],  // Correct: $field1 and $field2
                    30
                ]
            ]
        ]);

        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // 10 + 20 = 30
    }

    #[TestDox('$expr combined with regular field queries using $and')]
    public function testExprWithAnd(): void
    {
        $vars = [
            'category' => 'electronics',
            'price' => 100,
            'discount' => 20,
            'inStock' => true
        ];

        // Combine regular field query with $expr
        $qc = new QueryCheck([
            '$and' => [
                ['category' => 'electronics'],
                ['inStock' => true],
                [
                    '$expr' => [
                        '$lt' => [
                            ['$subtract' => ['$price', '$discount']],
                            90
                        ]
                    ]
                ]
            ]
        ]);

        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // category matches, inStock=true, and (100-20)=80 < 90
    }

    #[TestDox('$expr combined with $and returns false when expression fails')]
    public function testExprWithAndReturnsFalse(): void
    {
        $vars = [
            'category' => 'electronics',
            'price' => 100,
            'discount' => 10,
            'inStock' => true
        ];

        // Same query but discount is lower, so price-discount won't be < 90
        $qc = new QueryCheck([
            '$and' => [
                ['category' => 'electronics'],
                ['inStock' => true],
                [
                    '$expr' => [
                        '$lt' => [
                            ['$subtract' => ['$price', '$discount']],
                            90
                        ]
                    ]
                ]
            ]
        ]);

        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // (100-10)=90 is NOT < 90
    }

    // AGGREGATION $NOT OPERATOR TESTS

    #[TestDox('$expr with $not // $not[true] returns false')]
    public function testExprAggNotTrue(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [true]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[false] returns true')]
    public function testExprAggNotFalse(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [false]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[null] returns true')]
    public function testExprAggNotNull(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [null]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[0] returns true')]
    public function testExprAggNotZero(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [0]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[0.0] returns true (float zero is falsy in MongoDB)')]
    public function testExprAggNotFloatZero(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [0.0]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[1] returns false')]
    public function testExprAggNotOne(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [1]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[137] returns false')]
    public function testExprAggNotNonZeroNumber(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [137]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[[false]] returns false (array is truthy)')]
    public function testExprAggNotArrayWithFalse(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [[false]]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $not // $not[$gt[$qty, 250]]')]
    public function testExprAggNotWithGtExpression(): void
    {
        $vars = ['qty' => 200];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$gt' => ['$qty', 250]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // qty (200) is NOT > 250, so $not returns true
    }

    #[TestDox('$expr with $not // $not[$gt[$qty, 250]] returns false when qty > 250')]
    public function testExprAggNotWithGtExpressionFalse(): void
    {
        $vars = ['qty' => 300];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$gt' => ['$qty', 250]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // qty (300) > 250, so $not returns false
    }

    #[TestDox('$expr with $not // $not[$eq[field1, field2]] when fields are equal')]
    public function testExprAggNotWithEqEqual(): void
    {
        $vars = ['field1' => 100, 'field2' => 100];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$eq' => ['$field1', '$field2']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // fields are equal, so $not returns false
    }

    #[TestDox('$expr with $not // $not[$eq[field1, field2]] when fields are not equal')]
    public function testExprAggNotWithEqNotEqual(): void
    {
        $vars = ['field1' => 100, 'field2' => 200];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$eq' => ['$field1', '$field2']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // fields are not equal, so $not returns true
    }

    #[TestDox('$expr with $not // $not[$eq[field, literal]] when equal')]
    public function testExprAggNotWithEqLiteralEqual(): void
    {
        $vars = ['status' => 'active'];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$eq' => ['$status', 'active']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // status equals 'active', so $not returns false
    }

    #[TestDox('$expr with $not // $not[$eq[field, literal]] when not equal')]
    public function testExprAggNotWithEqLiteralNotEqual(): void
    {
        $vars = ['status' => 'inactive'];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$eq' => ['$status', 'active']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // status does not equal 'active', so $not returns true
    }

    // AGGREGATION $IN OPERATOR TESTS

    #[TestDox('$expr with $in // value in array returns true')]
    public function testExprAggInValueInArray(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['banana', ['apple', 'banana', 'cherry']]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $in // value not in array returns false')]
    public function testExprAggInValueNotInArray(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['orange', ['apple', 'banana', 'cherry']]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $in // field value in array')]
    public function testExprAggInFieldValueInArray(): void
    {
        $vars = ['fruit' => 'banana', 'in_stock' => ['apple', 'banana', 'cherry']];
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['$fruit', '$in_stock']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $in // field value not in array')]
    public function testExprAggInFieldValueNotInArray(): void
    {
        $vars = ['fruit' => 'orange', 'in_stock' => ['apple', 'banana', 'cherry']];
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['$fruit', '$in_stock']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars));
    }

    #[TestDox('$expr with $in // number in array')]
    public function testExprAggInNumberInArray(): void
    {
        $vars = ['value' => 137];
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['$value', [100, 137, 200]]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $in // null in array')]
    public function testExprAggInNullInArray(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => [null, [1, 2, null, 3]]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$expr with $in // empty array returns false')]
    public function testExprAggInEmptyArray(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['banana', '$myEmptyArray']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($this->vars));
    }

    #[TestDox('$expr with $in // object in array of objects')]
    public function testExprAggInObjectInArray(): void
    {
        $vars = ['item' => ['x' => 10, 'y' => 11], 'items' => [['x' => 10, 'y' => 11], ['x' => 20, 'y' => 21]]];
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => ['$item', '$items']
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars));
    }

    // COMBINED TESTS

    #[TestDox('$expr with $not and $in // $not[$in[value, array]]')]
    public function testExprAggNotWithIn(): void
    {
        $vars = ['fruit' => 'orange', 'in_stock' => ['apple', 'banana', 'cherry']];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => ['$fruit', '$in_stock']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // orange is NOT in stock, so $not returns true
    }

    #[TestDox('$expr with $not and $in // $not[$in[value, array]] returns false when value is in array')]
    public function testExprAggNotWithInFalse(): void
    {
        $vars = ['fruit' => 'banana', 'in_stock' => ['apple', 'banana', 'cherry']];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => ['$fruit', '$in_stock']]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // banana IS in stock, so $not returns false
    }

    #[TestDox('$expr with $not and $in // $not[$in[literal, literal array]]')]
    public function testExprAggNotWithInLiterals(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => ['grape', ['apple', 'banana', 'cherry']]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($this->vars)); // 'grape' is NOT in array, so $not returns true
    }

    #[TestDox('$expr with $not and $in // $not[$in[number, number array]]')]
    public function testExprAggNotWithInNumbers(): void
    {
        $vars = ['value' => 42];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => ['$value', [10, 20, 30, 40]]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // 42 is NOT in array, so $not returns true
    }

    #[TestDox('$expr with $not and $in // $not[$in[computed value, array]]')]
    public function testExprAggNotWithInComputedValue(): void
    {
        $vars = ['price' => 100, 'discount' => 30, 'invalid_prices' => [50, 60, 80]];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => [
                        ['$subtract' => ['$price', '$discount']],
                        '$invalid_prices'
                    ]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // 100 - 30 = 70, which is NOT in invalid_prices, so $not returns true
    }

    #[TestDox('$expr with $not and $in // $not[$in[computed value, array]] returns false when value is in array')]
    public function testExprAggNotWithInComputedValueFalse(): void
    {
        $vars = ['price' => 100, 'discount' => 20, 'invalid_prices' => [50, 60, 80]];
        $qc = new QueryCheck([
            '$expr' => [
                '$not' => [
                    ['$in' => [
                        ['$subtract' => ['$price', '$discount']],
                        '$invalid_prices'
                    ]]
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertFalse($qc->test($vars)); // 100 - 20 = 80, which IS in invalid_prices, so $not returns false
    }

    #[TestDox('$expr with $in and comparison // check if discounted price is in valid range')]
    public function testExprAggInWithComparison(): void
    {
        $vars = ['price' => 100, 'discount' => 20, 'valid_prices' => [50, 80, 100, 150]];
        $qc = new QueryCheck([
            '$expr' => [
                '$in' => [
                    ['$subtract' => ['$price', '$discount']],
                    '$valid_prices'
                ]
            ]
        ]);
        $qc->setStrictMode(false);
        $this->assertTrue($qc->test($vars)); // 100 - 20 = 80, which is in valid_prices
    }

    // $SIZE AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $size // returns array length')]
    public function testExprAggSize(): void
    {
        $vars = ['colors' => ['blue', 'black', 'red']];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$size' => '$colors'], 3]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $size // empty array returns 0')]
    public function testExprAggSizeEmpty(): void
    {
        $vars = ['colors' => []];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$size' => '$colors'], 0]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $size // use with $gt for minimum elements')]
    public function testExprAggSizeWithGt(): void
    {
        $vars = ['tags' => ['php', 'mongodb', 'querycheck', 'testing']];
        $qc = new QueryCheck([
            '$expr' => [
                '$gt' => [['$size' => '$tags'], 3]
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // 4 > 3
    }

    #[TestDox('$expr with $size // use with $lt for maximum elements')]
    public function testExprAggSizeWithLt(): void
    {
        $vars = ['items' => ['a', 'b']];
        $qc = new QueryCheck([
            '$expr' => [
                '$lt' => [['$size' => '$items'], 5]
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // 2 < 5
    }

    #[TestDox('$expr with $size // use with $gte')]
    public function testExprAggSizeWithGte(): void
    {
        $vars = ['items' => ['x', 'y', 'z']];
        $qc = new QueryCheck([
            '$expr' => [
                '$gte' => [['$size' => '$items'], 3]
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // 3 >= 3
    }

    #[TestDox('$expr with $size // throws on non-array')]
    public function testExprAggSizeThrowsOnNonArray(): void
    {
        $vars = ['name' => 'John'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$size' => '$name'], 4]
            ]
        ]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$size: argument must resolve to an array');
        $qc->test($vars);
    }

    #[TestDox('$expr with $size // throws on associative array (object)')]
    public function testExprAggSizeThrowsOnObject(): void
    {
        $vars = ['data' => ['x' => 1, 'y' => 2]];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$size' => '$data'], 2]
            ]
        ]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$size: argument must resolve to an array');
        $qc->test($vars);
    }

    #[TestDox('$expr with $size // combined with $cond')]
    public function testExprAggSizeWithCond(): void
    {
        $vars = ['items' => ['a', 'b', 'c', 'd', 'e']];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$cond' => [
                        'if' => ['$gte' => [['$size' => '$items'], 5]],
                        'then' => 'bulk',
                        'else' => 'single'
                    ]],
                    'bulk'
                ]
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // size is 5, so returns 'bulk'
    }

    #[TestDox('$expr with $size // with literal array')]
    public function testExprAggSizeWithLiteralArray(): void
    {
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$size' => [1, 2, 3, 4]], 4]
            ]
        ]);
        $this->assertTrue($qc->test(['dummy' => 'value']));
    }

    // ARRAY INDEX ACCESS TESTS

    #[TestDox('$expr with array index access // $myArray[0] and $myList[0].amount')]
    public function testExprArrayIndexAccess(): void
    {
        $vars = [
            'myArray' => [10, 20, 30],
            'myList' => [
                ['amount' => 100, 'name' => 'first'],
                ['amount' => 200, 'name' => 'second'],
            ],
        ];

        // Test simple array index access
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => ['$myArray[0]', 10]
            ]
        ]);
        $this->assertTrue($qc->test($vars));

        // Test nested array index with property access
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => ['$myList[0].amount', 100]
            ]
        ]);
        $this->assertTrue($qc->test($vars));

        // Test comparison between array elements
        $qc = new QueryCheck([
            '$expr' => [
                '$lt' => ['$myList[0].amount', '$myList[1].amount']
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // 100 < 200

        // Test arithmetic with array indexed values
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$add' => ['$myArray[0]', '$myArray[1]']],
                    30
                ]
            ]
        ]);
        $this->assertTrue($qc->test($vars)); // 10 + 20 = 30
    }

    #[TestDox('$expr with deeply nested array index access // $myObj.sub1.sub2.myList[0].amount')]
    public function testExprDeeplyNestedArrayIndexAccess(): void
    {
        $vars = [
            'myObj' => [
                'sub1' => [
                    'sub2' => [
                        'myList' => [
                            ['amount' => 42, 'name' => 'first'],
                            ['amount' => 99, 'name' => 'second'],
                        ],
                    ],
                ],
            ],
        ];

        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => ['$myObj.sub1.sub2.myList[0].amount', 42]
            ]
        ]);
        $this->assertTrue($qc->test($vars));

        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => ['$myObj.sub1.sub2.myList[1].name', 'second']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $and // check $size >= 1 before accessing myList[0]')]
    public function testExprAndWithSizeAndArrayAccess(): void
    {
        $vars = [
            'myList' => [
                ['amount' => 100, 'name' => 'first'],
            ],
        ];

        $qc = new QueryCheck([
            '$and' => [
                ['$expr' => ['$gte' => [['$size' => '$myList'], 1]]],
                ['$expr' => ['$eq' => ['$myList[0].amount', 100]]],
            ]
        ]);
        $this->assertTrue($qc->test($vars));

        // Test with empty array - short-circuits on first condition, never accesses myList[0]
        $varsEmpty = ['myList' => []];
        $this->assertFalse($qc->test($varsEmpty));
    }

    #[TestDox('$or short-circuit // empty list matches first branch, myList[0] is never accessed')]
    public function testOrShortCircuitsWithSizeAndArrayAccess(): void
    {
        $qc = new QueryCheck([
            '$or' => [
                ['$expr' => ['$eq' => [['$size' => '$myList'], 0]]],
                ['myList[0].amount' => 100],
            ]
        ]);

        // first branch matches: myList[0] would throw UnknownVariableException
        $this->assertTrue($qc->test(['myList' => []]));

        // first branch does not match: second branch is evaluated
        $this->assertTrue($qc->test(['myList' => [['amount' => 100]]]));
        $this->assertFalse($qc->test(['myList' => [['amount' => 5]]]));
    }

    #[TestDox('$or short-circuit // later branches still run until one matches')]
    public function testOrEvaluatesUntilFirstMatch(): void
    {
        $qc = new QueryCheck([
            '$or' => [
                ['a' => 1],
                ['missing' => 1],
            ]
        ]);

        $this->assertTrue($qc->test(['a' => 1]));

        // first branch is false, so the second runs and the missing field throws
        $this->expectException(UnknownVariableException::class);
        $qc->test(['a' => 2]);
    }

    #[TestDox('$or short-circuit // operand evaluator is not called for skipped branches')]
    public function testOrShortCircuitSkipsOperandEvaluator(): void
    {
        $seen = [];
        $qc = new QueryCheck([
            '$or' => [
                ['a' => ['$var' => 'x']],
                ['b' => ['$var' => 'y']],
            ]
        ]);
        $qc->setOperandEvaluator(function ($operand, $data) use (&$seen, $qc) {
            if (is_array($operand) && isset($operand['$var']) && is_string($operand['$var'])) {
                $seen[] = $operand['$var'];
                return $qc->getVariableValue($operand['$var'], $data);
            }
            return $operand;
        });

        $this->assertTrue($qc->test(['a' => 1, 'b' => 2, 'x' => 1, 'y' => 2]));
        $this->assertSame(['x'], $seen);
    }

    // $toLower / $toUpper AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $toLower // converts string to lowercase')]
    public function testExprAggToLower(): void
    {
        $vars = ['name' => 'JOHN DOE'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$toLower' => '$name'], 'john doe']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $toUpper // converts string to uppercase')]
    public function testExprAggToUpper(): void
    {
        $vars = ['name' => 'john doe'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$toUpper' => '$name'], 'JOHN DOE']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $toLower // case-insensitive comparison of two fields')]
    public function testExprAggToLowerCaseInsensitiveComparison(): void
    {
        $vars = ['field1' => 'Hello World', 'field2' => 'HELLO WORLD'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [
                    ['$toLower' => '$field1'],
                    ['$toLower' => '$field2']
                ]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $toLower // handles unicode characters')]
    public function testExprAggToLowerUnicode(): void
    {
        $vars = ['name' => 'MÜLLER'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$toLower' => '$name'], 'müller']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $toUpper // throws on non-string')]
    public function testExprAggToUpperThrowsOnNonString(): void
    {
        $vars = ['value' => 123];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$toUpper' => '$value'], '123']
            ]
        ]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$toUpper: argument must resolve to a string');
        $qc->test($vars);
    }

    // $substr / $substrBytes AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $substr // extracts substring by codepoint')]
    public function testExprAggSubstr(): void
    {
        $vars = ['text' => 'Hello World'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$substr' => ['$text', 0, 5]], 'Hello']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $substr // handles unicode codepoints')]
    public function testExprAggSubstrUnicode(): void
    {
        $vars = ['text' => 'Müller'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$substr' => ['$text', 0, 3]], 'Mül']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $substr // negative length returns rest of string')]
    public function testExprAggSubstrNegativeLength(): void
    {
        $vars = ['text' => 'Hello World'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$substr' => ['$text', 6, -1]], 'World']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $substrBytes // extracts substring by bytes')]
    public function testExprAggSubstrBytes(): void
    {
        $vars = ['text' => 'Hello World'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$substrBytes' => ['$text', 0, 5]], 'Hello']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $substrBytes // byte-based on multibyte string')]
    public function testExprAggSubstrBytesMultibyte(): void
    {
        $vars = ['text' => 'Müller']; // ü is 2 bytes in UTF-8
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$substrBytes' => ['$text', 0, 1]], 'M']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    // $strLenBytes AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $strLenBytes // returns byte length')]
    public function testExprAggStrLenBytes(): void
    {
        $vars = ['text' => 'Hello'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$strLenBytes' => '$text'], 5]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $strLenBytes // counts bytes for multibyte chars')]
    public function testExprAggStrLenBytesMultibyte(): void
    {
        $vars = ['text' => 'Müller']; // ü is 2 bytes, so 7 bytes total
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$strLenBytes' => '$text'], 7]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $strLenCP // returns codepoint length')]
    public function testExprAggStrLenCP(): void
    {
        $vars = ['text' => 'Hello'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$strLenCP' => '$text'], 5]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $strLenCP // counts codepoints for multibyte chars')]
    public function testExprAggStrLenCPMultibyte(): void
    {
        $vars = ['text' => 'Müller']; // 6 codepoints (ü is 1 codepoint)
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$strLenCP' => '$text'], 6]
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    // $trim / $ltrim / $rtrim AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $trim // removes whitespace from both ends')]
    public function testExprAggTrim(): void
    {
        $vars = ['text' => '  hello  '];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$trim' => ['input' => '$text']], 'hello']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $trim // removes specified chars')]
    public function testExprAggTrimWithChars(): void
    {
        $vars = ['text' => '---hello---'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$trim' => ['input' => '$text', 'chars' => '-']], 'hello']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $ltrim // removes whitespace from left')]
    public function testExprAggLtrim(): void
    {
        $vars = ['text' => '  hello  '];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$ltrim' => ['input' => '$text']], 'hello  ']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $rtrim // removes whitespace from right')]
    public function testExprAggRtrim(): void
    {
        $vars = ['text' => '  hello  '];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$rtrim' => ['input' => '$text']], '  hello']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    // $concat AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $concat // concatenates strings')]
    public function testExprAggConcat(): void
    {
        $vars = ['first' => 'Hello', 'second' => 'World'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$concat' => ['$first', ' ', '$second']], 'Hello World']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $concat // concatenates multiple strings')]
    public function testExprAggConcatMultiple(): void
    {
        $vars = ['a' => 'a', 'b' => 'b', 'c' => 'c'];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$concat' => ['$a', '$b', '$c']], 'abc']
            ]
        ]);
        $this->assertTrue($qc->test($vars));
    }

    #[TestDox('$expr with $concat // throws on non-string')]
    public function testExprAggConcatThrowsOnNonString(): void
    {
        $vars = ['text' => 'hello', 'num' => 123];
        $qc = new QueryCheck([
            '$expr' => [
                '$eq' => [['$concat' => ['$text', '$num']], 'hello123']
            ]
        ]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('$concat: all arguments must resolve to strings');
        $qc->test($vars);
    }

    // $literal AGGREGATION OPERATOR TESTS

    #[TestDox('$expr with $literal // a string with $ is compared as a string, not a field reference')]
    public function testExprAggLiteralDollarString(): void
    {
        // MongoDB's example: price == "$1"
        $qc = new QueryCheck(['$expr' => ['$eq' => ['$price', ['$literal' => '$1']]]]);
        $this->assertTrue($qc->test(['price' => '$1']));
        $this->assertFalse($qc->test(['price' => '$2.50']));
        $this->assertFalse($qc->test(['price' => '1']));
    }

    #[TestDox('$expr with $literal // without $literal, "$1" is a field reference')]
    public function testExprWithoutLiteralIsFieldReference(): void
    {
        $qc = new QueryCheck(['$expr' => ['$eq' => ['$price', '$1']]]);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertFalse($qc->test(['price' => '$1']));       // compares with the missing field "1"
        $this->assertTrue($qc->test(['price' => 5, '1' => 5]));  // the field named "1"
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function literalValueProvider(): array
    {
        return [
            'operator object' => [['$add' => [2, 3]]],
            'nested $literal' => [['$literal' => 1]],
            'list with field references' => [[1, '$x', ['$size' => '$y']]],
            'null' => [null],
            'number' => [1],
            'empty array' => [[]],
        ];
    }

    #[TestDox('$expr with $literal // returns the value unparsed')]
    #[\PHPUnit\Framework\Attributes\DataProvider('literalValueProvider')]
    public function testExprAggLiteralReturnsValueUnparsed(mixed $value): void
    {
        // the field holds exactly the value $literal returns
        $qc = new QueryCheck(['$expr' => ['$eq' => ['$v', ['$literal' => $value]]]]);
        $qc->validate();
        $this->assertTrue($qc->test(['v' => $value]));
    }

    #[TestDox('$expr with $literal // $literal: {$add: [2, 3]} is the object, not 5')]
    public function testExprAggLiteralDoesNotEvaluate(): void
    {
        $qc = new QueryCheck(['$expr' => ['$eq' => [['$literal' => ['$add' => [2, 3]]], 5]]]);
        $this->assertFalse($qc->test(['v' => 1]));
    }

    #[TestDox('$expr with $literal // contents are not checked, and not passed to the operand evaluator')]
    public function testExprAggLiteralIsOpaque(): void
    {
        // an unknown operator inside $literal is just data
        $qc = new QueryCheck(['$expr' => ['$eq' => ['$v', ['$literal' => ['$nope' => 1]]]]]);
        $qc->validate();
        $this->assertTrue($qc->test(['v' => ['$nope' => 1]]));

        $seen = [];
        $withEvaluator = new QueryCheck(['$expr' => ['$eq' => ['$v', ['$literal' => ['$var' => 'x']]]]]);
        $withEvaluator->setOperandEvaluator(function (mixed $operand, array $data) use (&$seen): mixed {
            $seen[] = $operand;
            return is_array($operand) && array_key_first($operand) === '$var' ? 'resolved' : $operand;
        });
        $this->assertTrue($withEvaluator->test(['v' => ['$var' => 'x']]));
        $this->assertNotContains(['$var' => 'x'], $seen);
    }

    #[TestDox('$expr with $literal // in $cond and $in')]
    public function testExprAggLiteralInOtherOperators(): void
    {
        $qc = new QueryCheck(['$expr' => ['$in' => [['$literal' => '$admin'], '$roles']]]);
        $this->assertTrue($qc->test(['roles' => ['user', '$admin']]));
        $this->assertFalse($qc->test(['roles' => ['user', 'admin']]));

        $qc = new QueryCheck(['$expr' => ['$eq' => [
            ['$cond' => ['if' => ['$gt' => ['$n', 0]], 'then' => ['$literal' => '$positive'], 'else' => '$label']],
            '$positive',
        ]]]);
        $this->assertTrue($qc->test(['n' => 1, 'label' => 'x', 'positive' => '$positive']));
    }

    // RELATIONAL OPERATOR TYPE TESTS ($gt / $gte / $lt / $lte)

    /**
     * Values that cannot be ordered against the operand must not match.
     * PHP's native comparison would let all of these through.
     *
     * @return array<string, array{mixed, string, mixed}>
     */
    public static function unorderableProvider(): array
    {
        return [
            'string "admin" $gte 18' => ['admin', '$gte', 18],
            'string "admin" $gt 999999' => ['admin', '$gt', 999999],
            'empty array $gte 18' => [[], '$gte', 18],
            'list $gt 999' => [['x'], '$gt', 999],
            'object $gte 18' => [['years' => 5], '$gte', 18],
            'null $lt 5' => [null, '$lt', 5],
            'null $lte 0' => [null, '$lte', 0],
            'null $gte 0' => [null, '$gte', 0],
            'false $lt 1' => [false, '$lt', 1],
            'false $gte 0' => [false, '$gte', 0],
            'true $gte 1' => [true, '$gte', 1],
            'true $gt 0' => [true, '$gt', 0],
            'number $gt string "admin"' => [137, '$gt', 'admin'],
            'number $lt operand null' => [1, '$lt', null],
            'number $lt operand true' => [0, '$lt', true],
        ];
    }

    #[TestDox('relational operators // an array literal operand is a SyntaxError, inside $expr it does not match')]
    public function testRelationalArrayOperand(): void
    {
        $expr = new QueryCheck(['$expr' => ['$lt' => ['$field', [1]]]]);
        $this->assertFalse($expr->test(['field' => 0]));

        $qc = new QueryCheck(['field' => ['$lt' => [1]]]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('field.$lt: $lt cannot compare with an array or object');
        $qc->test(['field' => 0]);
    }

    #[TestDox('relational operators // unorderable value does not match')]
    #[\PHPUnit\Framework\Attributes\DataProvider('unorderableProvider')]
    public function testRelationalUnorderableDoesNotMatch(mixed $value, string $operator, mixed $operand): void
    {
        $qc = new QueryCheck(['field' => [$operator => $operand]]);
        $this->assertFalse($qc->test(['field' => $value]));
    }

    #[TestDox('relational operators // $expr: unorderable value does not match')]
    #[\PHPUnit\Framework\Attributes\DataProvider('unorderableProvider')]
    public function testExprRelationalUnorderableDoesNotMatch(mixed $value, string $operator, mixed $operand): void
    {
        $qc = new QueryCheck(['$expr' => [$operator => ['$field', $operand]]]);
        $this->assertFalse($qc->test(['field' => $value]));
    }

    /**
     * @return array<string, array{mixed, string, mixed, bool}>
     */
    public static function orderableProvider(): array
    {
        return [
            'int $gte int' => [30, '$gte', 18, true],
            'int $gte int (false)' => [17, '$gte', 18, false],
            'float $gt int' => [18.5, '$gt', 18, true],
            'int $gte float' => [1, '$gte', 1.0, true],
            'numeric string $gt int' => ['137', '$gt', 136, true],
            'int $gt numeric string' => [137, '$gt', '136', true],
            'int $gt numeric string (false)' => [137, '$gt', '138', false],
            'string $gt string' => ['13:59', '$gt', '10:00', true],
            'string $lt string' => ['13:59', '$lt', '12:00', false],
        ];
    }

    #[TestDox('relational operators // orderable values still compare')]
    #[\PHPUnit\Framework\Attributes\DataProvider('orderableProvider')]
    public function testRelationalOrderable(mixed $value, string $operator, mixed $operand, bool $expected): void
    {
        $qc = new QueryCheck(['field' => [$operator => $operand]]);
        $this->assertSame($expected, $qc->test(['field' => $value]));

        $expr = new QueryCheck(['$expr' => [$operator => ['$field', $operand]]]);
        $this->assertSame($expected, $expr->test(['field' => $value]));
    }

    #[TestDox('relational operators // null intermediate does not pass a numeric bound')]
    public function testRelationalNullIntermediateDoesNotMatch(): void
    {
        $qc = new QueryCheck(['profile.age' => ['$gte' => 0, '$lt' => 120]]);
        $this->assertFalse($qc->test(['profile' => null]));
    }

    #[TestDox('[STRICT] relational operators // null value does not match and does not throw')]
    public function testStrictRelationalNullDoesNotMatch(): void
    {
        $qc = new QueryCheck(['failedAttempts' => ['$lt' => 5]]);
        $qc->setStrictMode(true);
        $this->assertFalse($qc->test(['failedAttempts' => null]));
    }

    #[TestDox('[STRICT] relational operators // same unorderable type throws')]
    public function testStrictRelationalArrayThrows(): void
    {
        // an array literal operand is a syntax error; two arrays can still
        // meet inside $expr
        $qc = new QueryCheck(['$expr' => ['$gte' => ['$list', ['$other']]]]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$gte: first operand is of type array, which cannot be ordered');
        $qc->test(['list' => [1], 'other' => 1]);
    }

    /**
     * Booleans order against booleans (false < true), null against null
     * (equal), in both modes. Against other types they still do not match.
     *
     * @return array<string, array{array<mixed>, mixed, bool}>
     */
    public static function boolNullOrderingProvider(): array
    {
        return [
            'true $gte true' => [['v' => ['$gte' => true]], true, true],
            'false $lt true' => [['v' => ['$lt' => true]], false, true],
            'true $gt false' => [['v' => ['$gt' => false]], true, true],
            'true $gt true' => [['v' => ['$gt' => true]], true, false],
            'null $gte null' => [['v' => ['$gte' => null]], null, true],
            'null $lte null' => [['v' => ['$lte' => null]], null, true],
            'null $gt null' => [['v' => ['$gt' => null]], null, false],
            'null $lt null' => [['v' => ['$lt' => null]], null, false],
            '$expr true $gte true' => [['$expr' => ['$gte' => ['$v', true]]], true, true],
            '$expr null $lte null' => [['$expr' => ['$lte' => ['$v', null]]], null, true],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('relational operators // booleans and null order against their own type')]
    #[\PHPUnit\Framework\Attributes\DataProvider('boolNullOrderingProvider')]
    public function testBoolNullOrdering(array $query, mixed $value, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test(['v' => $value]));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertSame($expected, $strict->test(['v' => $value]));
    }

    /**
     * PHP compares an integer with a float by converting the integer, which
     * makes 2^53 + 1 equal to 2^53.0. These compare exactly.
     *
     * @return array<string, array{array<mixed>, mixed, bool}>
     */
    public static function bigNumberProvider(): array
    {
        $big = 2 ** 53 + 1;          // 9007199254740993, not representable as float
        $bigFloat = (float)(2 ** 53); // 9007199254740992.0
        return [
            '2^53+1 $eq 2^53.0' => [['v' => $bigFloat], $big, false],
            '2^53+1 $ne 2^53.0' => [['v' => ['$ne' => $bigFloat]], $big, true],
            '2^53+1 $gt 2^53.0' => [['v' => ['$gt' => $bigFloat]], $big, true],
            '2^53+1 $lte 2^53.0' => [['v' => ['$lte' => $bigFloat]], $big, false],
            '2^53.0 $lt 2^53+1' => [['v' => ['$lt' => $big]], $bigFloat, true],
            '2^53 $eq 2^53.0' => [['v' => $bigFloat], 2 ** 53, true],
            '2^53+1 $in [2^53.0]' => [['v' => ['$in' => [$bigFloat]]], $big, false],
            'PHP_INT_MAX $lt INF' => [['v' => ['$lt' => INF]], PHP_INT_MAX, true],
            'PHP_INT_MAX $lt 2^63.0' => [['v' => ['$lt' => 9.2233720368547758E18]], PHP_INT_MAX, true],
            '$expr 2^53+1 $gt 2^53.0' => [['$expr' => ['$gt' => ['$v', $bigFloat]]], $big, true],
            '$expr 2^53+1 $eq 2^53.0' => [['$expr' => ['$eq' => ['$v', $bigFloat]]], $big, false],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('numbers beyond 2^53 // integer vs float compared exactly')]
    #[\PHPUnit\Framework\Attributes\DataProvider('bigNumberProvider')]
    public function testBigNumbersComparedExactly(array $query, mixed $value, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test(['v' => $value]));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertSame($expected, $strict->test(['v' => $value]));
    }

    // ARRAY FIELD TESTS ($eq / $ne / $in, like MongoDB)

    /**
     * @return array<string, array{array<mixed>, mixed, bool}>
     */
    public static function arrayFieldProvider(): array
    {
        return [
            '$ne "banned" on ["banned"]' => [['role' => ['$ne' => 'banned']], ['banned'], false],
            '$ne "banned" on ["banned", "admin"]' => [['role' => ['$ne' => 'banned']], ['banned', 'admin'], false],
            '$ne "banned" on ["admin"]' => [['role' => ['$ne' => 'banned']], ['admin'], true],
            '$ne "banned" on []' => [['role' => ['$ne' => 'banned']], [], true],
            '$ne ["a", "b"] on ["a", "b"]' => [['role' => ['$ne' => ['a', 'b']]], ['a', 'b'], false],
            '$ne 1 on [1.0]' => [['role' => ['$ne' => 1]], [1.0], false],
            '$in ["admin"] on ["admin"]' => [['role' => ['$in' => ['admin']]], ['admin'], true],
            '$in ["admin"] on ["user", "admin"]' => [['role' => ['$in' => ['admin']]], ['user', 'admin'], true],
            '$in ["admin"] on ["user"]' => [['role' => ['$in' => ['admin']]], ['user'], false],
            '$in ["admin"] on []' => [['role' => ['$in' => ['admin']]], [], false],
            '$in [["a", "b"]] on ["a", "b"]' => [['role' => ['$in' => [['a', 'b']]]], ['a', 'b'], true],
            '$not $in ["banned"] on ["banned", "admin"]' => [['role' => ['$not' => ['$in' => ['banned']]]], ['banned', 'admin'], false],
            '$not $in ["banned"] on ["admin"]' => [['role' => ['$not' => ['$in' => ['banned']]]], ['admin'], true],
            '$eq [1] on [1, 5] (not contained as element)' => [['role' => [1]], [1, 5], false],
            '$eq "admin" on ["user", "admin"]' => [['role' => 'admin'], ['user', 'admin'], true],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('array field // $eq, $ne and $in match elements, $ne is the negation of $eq')]
    #[\PHPUnit\Framework\Attributes\DataProvider('arrayFieldProvider')]
    public function testArrayField(array $query, mixed $value, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test(['role' => $value]));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertSame($expected, $strict->test(['role' => $value]));
    }

    #[TestDox('array field // $eq and $ne never both match')]
    public function testArrayFieldEqNeExclusive(): void
    {
        $values = [['banned'], ['banned', 'admin'], ['admin'], [], [1, 2], [[1, 2]], ['1'], [1.0], [null], ['a', null]];
        $operands = ['banned', 'admin', 1, '1', [1, 2], [], null];
        foreach ($values as $value) {
            foreach ($operands as $operand) {
                $eq = (new QueryCheck(['v' => ['$eq' => $operand]]))->test(['v' => $value]);
                $ne = (new QueryCheck(['v' => ['$ne' => $operand]]))->test(['v' => $value]);
                $this->assertNotSame($eq, $ne, json_encode($value) . ' vs ' . json_encode($operand));
            }
        }
    }

    #[TestDox('[STRICT] $expr relational operators // type mismatch throws')]
    public function testStrictExprRelationalThrows(): void
    {
        $qc = new QueryCheck(['$expr' => ['$gte' => ['$age', 18]]]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$gte: first operand is of type string while second operand is of type integer');
        $qc->test(['age' => 'admin']);
    }

    #[TestDox('[STRICT] $expr relational operators // same type compares')]
    public function testStrictExprRelationalSameType(): void
    {
        $qc = new QueryCheck(['$expr' => ['$gte' => ['$age', 18]]]);
        $qc->setStrictMode(true);
        $this->assertTrue($qc->test(['age' => 30]));
        $this->assertFalse($qc->test(['age' => 17]));
    }

    // INTEGER VS FLOAT TESTS (one numeric type, like MongoDB)

    /**
     * @return array<string, array{array<mixed>, array<mixed>, bool}>
     */
    public static function intFloatProvider(): array
    {
        return [
            '$gt float vs int' => [['v' => ['$gt' => 10]], ['v' => 12.1], true],
            '$lt int vs float' => [['v' => ['$lt' => 12.5]], ['v' => 12], true],
            '$gte int vs float (equal)' => [['v' => ['$gte' => 12.0]], ['v' => 12], true],
            '$eq int vs float' => [['v' => 12], ['v' => 12.0], true],
            '$eq float vs int (not equal)' => [['v' => 12], ['v' => 12.1], false],
            '$ne int vs float' => [['v' => ['$ne' => 1]], ['v' => 1.0], false],
            '$ne int vs float (not equal)' => [['v' => ['$ne' => 1]], ['v' => 1.5], true],
            '$in int vs float' => [['v' => ['$in' => [12, 13]]], ['v' => 12.0], true],
            '$eq list contains float' => [['v' => 12], ['v' => [11.0, 12.0]], true],
            '$expr $gt float vs int' => [['$expr' => ['$gt' => ['$v', 10]]], ['v' => 12.1], true],
            '$expr $gt $divide result' => [['$expr' => ['$gt' => [['$divide' => ['$v', 5]], 2]]], ['v' => 12], true],
            '$expr $eq int vs float' => [['$expr' => ['$eq' => ['$v', 12]]], ['v' => 12.0], true],
            '$expr $ne int vs float' => [['$expr' => ['$ne' => ['$v', 12]]], ['v' => 12.0], false],
            '$expr $in int vs float' => [['$expr' => ['$in' => ['$v', [12]]]], ['v' => 12.0], true],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $data
     */
    #[TestDox('integer vs float // compared as numbers')]
    #[\PHPUnit\Framework\Attributes\DataProvider('intFloatProvider')]
    public function testIntFloatComparedAsNumbers(array $query, array $data, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test($data));
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $data
     */
    #[TestDox('[STRICT] integer vs float // compared as numbers, no StrictTypeError')]
    #[\PHPUnit\Framework\Attributes\DataProvider('intFloatProvider')]
    public function testStrictIntFloatComparedAsNumbers(array $query, array $data, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $qc->setStrictMode(true);
        $this->assertSame($expected, $qc->test($data));
    }

    // STRING VS STRING TESTS (compared as text, like MongoDB)

    /**
     * @return array<string, array{array<mixed>, array<mixed>, bool}>
     */
    public static function stringOrderingProvider(): array
    {
        return [
            '"12.0" $gt "12"' => [['v' => ['$gt' => '12']], ['v' => '12.0'], true],
            '"12.0" $lte "12"' => [['v' => ['$lte' => '12']], ['v' => '12.0'], false],
            '"10" $gt "9" (text: "1" < "9")' => [['v' => ['$gt' => '9']], ['v' => '10'], false],
            '"10" $lt "9"' => [['v' => ['$lt' => '9']], ['v' => '10'], true],
            '"1e3" in range "1000".."1000"' => [['v' => ['$gte' => '1000', '$lte' => '1000']], ['v' => '1e3'], false],
            '"1000" in range "1000".."1000"' => [['v' => ['$gte' => '1000', '$lte' => '1000']], ['v' => '1000'], true],
            '"abc" $lt "abd"' => [['v' => ['$lt' => 'abd']], ['v' => 'abc'], true],
            'zero-padded time' => [['v' => ['$gte' => '09:00', '$lt' => '18:00']], ['v' => '13:59'], true],
            '$expr "10" $gt "9"' => [['$expr' => ['$gt' => ['$v', '9']]], ['v' => '10'], false],
            '$expr "12.0" $gt "12"' => [['$expr' => ['$gt' => ['$v', '12']]], ['v' => '12.0'], true],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $data
     */
    #[TestDox('string vs string // compared as text')]
    #[\PHPUnit\Framework\Attributes\DataProvider('stringOrderingProvider')]
    public function testStringOrderingAsText(array $query, array $data, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test($data));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertSame($expected, $strict->test($data));
    }

    #[TestDox('numeric string vs number // still compared as numbers in non-strict mode')]
    public function testNumericStringVsNumberStillNumeric(): void
    {
        $qc = new QueryCheck(['v' => ['$gt' => 9]]);
        $this->assertTrue($qc->test(['v' => '10']));

        $qc = new QueryCheck(['v' => ['$gt' => '9']]);
        $this->assertTrue($qc->test(['v' => 10]));
    }

    // NAN TESTS (NaN equals NaN, like MongoDB)

    /**
     * @return array<string, array{array<mixed>, bool}>
     */
    public static function nanProvider(): array
    {
        return [
            '$eq NaN' => [['v' => NAN], true],
            '$ne NaN' => [['v' => ['$ne' => NAN]], false],
            '$in [NaN]' => [['v' => ['$in' => [NAN]]], true],
            '$gte NaN' => [['v' => ['$gte' => NAN]], true],
            '$lte NaN' => [['v' => ['$lte' => NAN]], true],
            '$gt NaN' => [['v' => ['$gt' => NAN]], false],
            '$lt NaN' => [['v' => ['$lt' => NAN]], false],
            '$gt 0' => [['v' => ['$gt' => 0]], false],
            '$lt 0' => [['v' => ['$lt' => 0]], false],
            '$eq 0' => [['v' => 0], false],
            '$expr $eq NaN' => [['$expr' => ['$eq' => ['$v', NAN]]], true],
            '$expr $ne NaN' => [['$expr' => ['$ne' => ['$v', NAN]]], false],
            '$expr $gte NaN' => [['$expr' => ['$gte' => ['$v', NAN]]], true],
        ];
    }

    /**
     * @param array<mixed> $query
     */
    #[TestDox('NaN // equals NaN, matches no ordering against numbers')]
    #[\PHPUnit\Framework\Attributes\DataProvider('nanProvider')]
    public function testNaN(array $query, bool $expected): void
    {
        $qc = new QueryCheck($query);
        $this->assertSame($expected, $qc->test(['v' => NAN]));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertSame($expected, $strict->test(['v' => NAN]));
    }

    #[TestDox('NaN // $eq with a string does not match and does not warn')]
    public function testNaNVsStringDoesNotWarn(): void
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            $qc = new QueryCheck(['v' => NAN]);
            $this->assertFalse($qc->test(['v' => 'NAN']));

            $qc = new QueryCheck(['v' => 'NAN']);
            $this->assertFalse($qc->test(['v' => NAN]));
        } finally {
            restore_error_handler();
        }
    }

    // STRICT MODE: NO MIXING STRINGS AND NUMBERS

    /**
     * @return array<string, array{array<mixed>, array<mixed>}>
     */
    public static function stringNumberEqualityProvider(): array
    {
        return [
            '$in [137] on "137"' => [['v' => ['$in' => [137]]], ['v' => '137']],
            '$in ["137"] on 137' => [['v' => ['$in' => ['137']]], ['v' => 137]],
            '$eq 1 on list ["1"]' => [['v' => 1], ['v' => ['1']]],
            '$expr $eq ["$v", 137] on "137"' => [['$expr' => ['$eq' => ['$v', 137]]], ['v' => '137']],
            '$expr $in ["$v", [137]] on "137"' => [['$expr' => ['$in' => ['$v', [137]]]], ['v' => '137']],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<mixed> $data
     */
    #[TestDox('string vs number equality // matches in non-strict mode only')]
    #[\PHPUnit\Framework\Attributes\DataProvider('stringNumberEqualityProvider')]
    public function testStringNumberEqualityNonStrictOnly(array $query, array $data): void
    {
        $qc = new QueryCheck($query);
        $this->assertTrue($qc->test($data));

        $strict = new QueryCheck($query);
        $strict->setStrictMode(true);
        $this->assertFalse($strict->test($data));
    }

    #[TestDox('[STRICT] $in // mixed-type list still works')]
    public function testStrictInMixedList(): void
    {
        $qc = new QueryCheck(['v' => ['$in' => ['a', 1, 2.0]]]);
        $qc->setStrictMode(true);
        $this->assertTrue($qc->test(['v' => 2]));
        $this->assertTrue($qc->test(['v' => 'a']));
        $this->assertFalse($qc->test(['v' => '1']));
    }

    #[TestDox('[STRICT] $eq // string vs number still throws')]
    public function testStrictEqStringVsNumberThrows(): void
    {
        $qc = new QueryCheck(['v' => 12]);
        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $this->expectExceptionMessage('$eq: variable v is of type string while operand is of type integer');
        $qc->test(['v' => '12']);
    }

    // COMPILED QUERY TESTS

    #[TestDox('compiled query // reused across calls with different documents')]
    public function testCompiledQueryIsReused(): void
    {
        $qc = new QueryCheck(['age' => ['$gte' => 18], 'country' => ['$in' => ['DE', 'AT']]]);
        $this->assertTrue($qc->test(['age' => 30, 'country' => 'DE']));
        $this->assertFalse($qc->test(['age' => 12, 'country' => 'DE']));
        $this->assertFalse($qc->test(['age' => 30, 'country' => 'FR']));
        $this->assertTrue($qc->test(['age' => 18, 'country' => 'AT']));
    }

    #[TestDox('compiled query // changing a setting after test() takes effect')]
    public function testSettingChangeAfterTestRecompiles(): void
    {
        $qc = new QueryCheck(['myInt' => ['$gt' => '136']]);
        $this->assertTrue($qc->test($this->vars));

        $qc->setStrictMode(true);
        $this->expectException(StrictTypeError::class);
        $qc->test($this->vars);
    }

    #[TestDox('compiled query // setThrowOnUnknownVariable after test() takes effect')]
    public function testThrowOnUnknownVariableAfterTestRecompiles(): void
    {
        $qc = new QueryCheck(['missing' => null]);
        $qc->setThrowOnUnknownVariable(false);
        $this->assertTrue($qc->test(['other' => 1]));

        $qc->setThrowOnUnknownVariable(true);
        $this->expectException(UnknownVariableException::class);
        $qc->test(['other' => 1]);
    }

    #[TestDox('compiled query // syntax errors are reported for the whole query')]
    public function testSyntaxErrorInUnreachedBranchThrows(): void
    {
        // The first condition is false, so the second one is never evaluated,
        // but the query is still invalid.
        $qc = new QueryCheck(['$and' => [['myInt' => 0], ['$unknown' => 1]]]);
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unsupported boolean operator: $unknown');
        $qc->test($this->vars);
    }

    #[TestDox('compiled query // instance is freed without the cycle collector')]
    public function testInstanceIsFreedWithoutCycleCollection(): void
    {
        $qc = new QueryCheck([
            'myInt' => ['$gte' => 18],
            '$expr' => ['$gt' => [['$add' => ['$myInt', 1]], 100]],
        ]);
        $this->assertTrue($qc->test($this->vars));

        $ref = \WeakReference::create($qc);
        unset($qc);

        $this->assertNull($ref->get(), 'QueryCheck must not keep itself alive through a reference cycle');
    }
}