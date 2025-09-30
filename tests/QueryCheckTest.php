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
}