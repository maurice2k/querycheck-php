<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Tests;

use Maurice2k\QueryCheck\QueryCheck;
use Maurice2k\QueryCheck\Exception\StrictTypeError;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class QueryCheckTest extends TestCase
{
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

    #[TestDox('$eq short syntax non-existant sub-variable // undefined throws exception')]
    public function testEqShortSyntaxNonExistantSubVariableUndefinedThrowsException(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => 'some string']);
        $qc->setStrictMode(false);
        $this->expectException(UnknownVariableException::class);
        $qc->test($this->vars);
    }

    #[TestDox('$eq short syntax non-existant sub-variable // undefined == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxNonExistantSubVariableUndefinedEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
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

    #[TestDox('$eq short syntax // undefined == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxUndefinedEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myUndefined' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
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

    #[TestDox('$eq short syntax // array[20] == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxArrayOutOfBoundsEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[20]' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // array[20].a.b.c.d == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxArrayDeepNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myArrayOfObjects[20].a.b.c.d' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // myNull.but.this.does.not.exist == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxNullPropertyNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myNull.but.this.does.not.exist' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('$eq short syntax // myInt.but.this.does.not.exist == null with setUndefinedEqualsNull(true)')]
    public function testEqShortSyntaxIntPropertyNonExistantEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myInt.but.this.does.not.exist' => null]);
        $qc->setStrictMode(false);
        $qc->setUndefinedEqualsNull(true);
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

    #[TestDox('[STRICT] $eq short syntax non-existant sub-variable // undefined == null with setUndefinedEqualsNull(true)')]
    public function testStrictEqShortSyntaxNonExistantSubVariableUndefinedEqualsNullWithFlag(): void
    {
        $qc = new QueryCheck(['myObject.myUndefined' => null]);
        $qc->setStrictMode(true);
        $qc->setUndefinedEqualsNull(true);
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

    #[TestDox('[STRICT] $ne full syntax // int[137] != string["138"]')]
    public function testStrictNeFullSyntaxIntNotEqualsString138(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '138']]);
        $qc->setStrictMode(true);
        $this->assertTrue($qc->test($this->vars));
    }

    #[TestDox('[STRICT] $ne full syntax // int[137] != string["137"]')]
    public function testStrictNeFullSyntaxIntNotEqualsString137(): void
    {
        $qc = new QueryCheck(['myInt' => ['$ne' => '137']]);
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
        $opEvaluator = null;

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

            if (!isset($params['map']) || $params['map'] === null || !is_array($params['map'])) {
                return $default;
            }

            $map = $opEvaluator($params['map'], $data);

            return $map[$key] ?? $default;
        };

        $varFunc = function ($params, $data) use ($qc) {
            if (is_string($params)) {
                // shortcut syntax {"$var": "varName"}
                $params = ['name' => $params];
            } elseif (!isset($params['name'])) {
                return null;
            }

            return $qc->getVariableValue($params['name'], $data);
        };

        $opEvalFuncs = [
            '$concat' => $concatFunc,
            '$lookup' => $lookupFunc,
            '$var' => $varFunc,
        ];

        $opEvaluator = function ($operand, $data) use ($opEvalFuncs) {
            $isObject = is_array($operand) && !array_is_list($operand) && $operand !== null;
            if (!$isObject) {
                return $operand;
            }

            $firstKey = array_keys($operand)[0] ?? null;
            if ($firstKey !== null && isset($opEvalFuncs[$firstKey])) {
                return $opEvalFuncs[$firstKey]($operand[$firstKey], $data);
            }

            return $operand;
        };

        return $opEvaluator;
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
                '$in' => ['banana', []]
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
}