# QueryCheck for PHP

*QueryCheck* is a logical JSON query evaluator which uses the [MongoDB™ query style](https://docs.mongodb.com/manual/tutorial/query-documents/).

This is a PHP 8.3+ port of the original [JavaScript/Node.js querycheck package](https://github.com/maurice2k/querycheck). Both packages support the same query and `$expr` aggregation operators. The PHP version has its own comparison rules, so it does not inherit PHP's type juggling (see [Comparison Rules](#comparison-rules), [Strict Mode](#strict-mode), [Differences from MongoDB](#differences-from-mongodb) and [Differences from the JavaScript Version](#differences-from-the-javascript-version)).

## Supported Operators

### Comparison Operators
* [$eq](https://docs.mongodb.com/manual/reference/operator/query/eq/) - Matches values that are equal to a specified value
* [$ne](https://docs.mongodb.com/manual/reference/operator/query/ne/) - Matches values that are not equal to a specified value
* [$gt](https://docs.mongodb.com/manual/reference/operator/query/gt/) - Matches values that are greater than a specified value
* [$gte](https://docs.mongodb.com/manual/reference/operator/query/gte/) - Matches values that are greater than or equal to a specified value
* [$lt](https://docs.mongodb.com/manual/reference/operator/query/lt/) - Matches values that are less than a specified value
* [$lte](https://docs.mongodb.com/manual/reference/operator/query/lte/) - Matches values that are less than or equal to a specified value
* [$in](https://docs.mongodb.com/manual/reference/operator/query/in/) - Matches any of the values specified in an array
* [$regex](https://docs.mongodb.com/manual/reference/operator/query/regex/) - Matches values using regular expression pattern matching

### Logical Operators
* [$and](https://docs.mongodb.com/manual/reference/operator/query/and/) - Joins query clauses with a logical AND
* [$or](https://docs.mongodb.com/manual/reference/operator/query/or/) - Joins query clauses with a logical OR
* [$not](https://docs.mongodb.com/manual/reference/operator/query/not/) - Inverts the effect of a query expression

### Aggregation Expressions (`$expr`)
* [$expr](https://www.mongodb.com/docs/manual/reference/operator/query/expr/) - Allows aggregation expressions within query predicates

The following operators can be used within `$expr`:

**Arithmetic Operators:**
* [$add](https://www.mongodb.com/docs/manual/reference/operator/aggregation/add/) - Adds numbers together
* [$subtract](https://www.mongodb.com/docs/manual/reference/operator/aggregation/subtract/) - Subtracts two numbers
* [$multiply](https://www.mongodb.com/docs/manual/reference/operator/aggregation/multiply/) - Multiplies numbers together
* [$divide](https://www.mongodb.com/docs/manual/reference/operator/aggregation/divide/) - Divides two numbers
* [$mod](https://www.mongodb.com/docs/manual/reference/operator/aggregation/mod/) - Returns the modulo of two numbers

**Query Operators** (for aggregation context):
* [$eq](https://www.mongodb.com/docs/manual/reference/operator/aggregation/eq/) - Returns true if values are equal
* [$ne](https://www.mongodb.com/docs/manual/reference/operator/aggregation/ne/) - Returns true if values are not equal
* [$gt](https://www.mongodb.com/docs/manual/reference/operator/aggregation/gt/) - Returns true if first value is greater than second
* [$gte](https://www.mongodb.com/docs/manual/reference/operator/aggregation/gte/) - Returns true if first value is greater than or equal to second
* [$lt](https://www.mongodb.com/docs/manual/reference/operator/aggregation/lt/) - Returns true if first value is less than second
* [$lte](https://www.mongodb.com/docs/manual/reference/operator/aggregation/lte/) - Returns true if first value is less than or equal to second

**Logical Operators:**
* [$not](https://www.mongodb.com/docs/manual/reference/operator/aggregation/not/) - Returns the boolean opposite of an expression
* [$in](https://www.mongodb.com/docs/manual/reference/operator/aggregation/in/) - Returns true if a value is in an array

**Array Operators:**
* [$size](https://www.mongodb.com/docs/manual/reference/operator/aggregation/size/) - Returns the number of elements in an array

**String Operators:**
* [$toLower](https://www.mongodb.com/docs/manual/reference/operator/aggregation/toLower/) - Converts a string to lowercase
* [$toUpper](https://www.mongodb.com/docs/manual/reference/operator/aggregation/toUpper/) - Converts a string to uppercase
* [$concat](https://www.mongodb.com/docs/manual/reference/operator/aggregation/concat/) - Concatenates strings
* [$substr](https://www.mongodb.com/docs/manual/reference/operator/aggregation/substr/) - Returns a substring (codepoint-based)
* [$substrBytes](https://www.mongodb.com/docs/manual/reference/operator/aggregation/substrBytes/) - Returns a substring (byte-based)
* [$strLenBytes](https://www.mongodb.com/docs/manual/reference/operator/aggregation/strLenBytes/) - Returns the byte length of a string
* [$strLenCP](https://www.mongodb.com/docs/manual/reference/operator/aggregation/strLenCP/) - Returns the codepoint length of a string
* [$trim](https://www.mongodb.com/docs/manual/reference/operator/aggregation/trim/) - Removes whitespace or characters from both ends
* [$ltrim](https://www.mongodb.com/docs/manual/reference/operator/aggregation/ltrim/) - Removes whitespace or characters from the start
* [$rtrim](https://www.mongodb.com/docs/manual/reference/operator/aggregation/rtrim/) - Removes whitespace or characters from the end

**Conditional Operators:**
* [$cond](https://www.mongodb.com/docs/manual/reference/operator/aggregation/cond/) - Conditional expression with if/then/else branches

**Literal Operators:**
* [$literal](https://www.mongodb.com/docs/manual/reference/operator/aggregation/literal/) - Returns a value without parsing it, so `"$1"` stays a string instead of a field reference

**Field References:**
Field references within `$expr` use MongoDB syntax with `$` prefix (e.g., `$fieldName`, `$customer.address.city`).

## Installation

Install with Composer:

```bash
composer require maurice2k/querycheck
```

## Simple Example

```php
<?php

use Maurice2k\QueryCheck\QueryCheck;

$vars = [
    'now' => [
        'isoDate' => '2020-05-21',
        'isoTime' => '13:59:48',
    ]
];

$openingHours = new QueryCheck([
    'now.isoDate' => [
        '$not' => [
            '$in' => ['2019-12-25', '2019-12-26', '2019-12-31', '2020-01-01']
        ]
    ],
    'now.isoTime' => [
        '$gt' => '10:00',
        '$lt' => '18:00'
    ]
]);

if ($openingHours->test($vars)) {
    echo "We're OPEN!\n";
} else {
    echo "Sorry, we're CLOSED!\n";
}
```

## Using `$expr` for Aggregation Expressions

The `$expr` operator allows you to use aggregation expressions for field-to-field comparisons and calculations:

```php
<?php

use Maurice2k\QueryCheck\QueryCheck;

// Compare two fields
$budget = new QueryCheck([
    '$expr' => [
        '$gt' => ['$spent', '$budget']  // spent > budget
    ]
]);

$data = ['spent' => 450, 'budget' => 400];
$budget->test($data); // returns true

// Arithmetic operations
$total = new QueryCheck([
    '$expr' => [
        '$gte' => [
            ['$add' => ['$subtotal', '$tax', '$shipping']],
            100
        ]
    ]
]);

$data = ['subtotal' => 80, 'tax' => 10, 'shipping' => 15];
$total->test($data); // returns true (80 + 10 + 15 = 105 >= 100)

// Conditional expressions
$discount = new QueryCheck([
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

$data = ['qty' => 150, 'price' => 100];
$discount->test($data); // returns true (qty >= 100, so discounted price = 100 * 0.5 = 50, and 50 < 60 is true)

// Logical operators - $not
$notGreater = new QueryCheck([
    '$expr' => [
        '$not' => [
            ['$gt' => ['$qty', 250]]
        ]
    ]
]);

$data = ['qty' => 200];
$notGreater->test($data); // returns true (qty is NOT > 250)

// Logical operators - $in
$inStock = new QueryCheck([
    '$expr' => [
        '$in' => ['$fruit', '$in_stock']
    ]
]);

$data = ['fruit' => 'banana', 'in_stock' => ['apple', 'banana', 'cherry']];
$inStock->test($data); // returns true (banana is in the in_stock array)

// Combining $not and $in
$notRestricted = new QueryCheck([
    '$expr' => [
        '$not' => [
            ['$in' => ['$category', '$restricted_categories']]
        ]
    ]
]);

$data = ['category' => 'electronics', 'restricted_categories' => ['weapons', 'alcohol']];
$notRestricted->test($data); // returns true (electronics is NOT in restricted categories)
```

**Notes:**
* Field references in `$expr` require the `$` prefix (e.g., `$fieldName`). Without the prefix, values are treated as literals.
* A string that starts with `$` is always a field reference. Wrap values that may start with `$`, such as user input, in `$literal`: `['$eq' => ['$price', ['$literal' => $userInput]]]`. Otherwise `"$role"` would compare with the field `role`. `$literal` returns its value without evaluating anything inside, also `{$add: [2, 3]}` stays that object.
* `$expr` can be combined with `$and` and `$or` operators to mix regular field queries with aggregation expressions.
* The aggregation `$not` operator evaluates `false`, `null`, and `0` as false; all other values (including non-zero numbers and arrays) as true.
* The aggregation `$in` operator checks if a value exists in an array, similar to the query `$in` but used within expressions.

## Comparison Rules

These rules apply to the query operators and to the same operators inside `$expr`, with and without strict mode unless noted.

### Types

PHP uses arrays for both lists and objects. QueryCheck treats a sequential array (`[1, 2]`) as an array and an associative array (`['x' => 1]`) as an object. Integers and floats are one numeric type everywhere: `12.1 > 10` is true, `{v: 12}` matches `12.0`, and `{v: {$ne: 1}}` does not match `1.0`. This matters because JSON encoders often write `137.0` as `137`.

### Equality (`$eq`, `$ne`, `$in`)

* Values of the same type are compared as they are: `"abc"` only equals `"abc"`, objects are equal if they have the same keys and equal values.
* Numbers compare by value, also beyond 2^53 (`9007199254740993` does not equal `9007199254740992.0`). `NaN` equals `NaN`, as in MongoDB.
* `null` only equals `null`.
* Without strict mode, a string and a number are equal if the number written as text is the string: `"137"` equals `137`, `"137.0"` does not. In strict mode a string never equals a number (see [Strict Mode](#strict-mode)).
* `$ne` is always the negation of `$eq`.

### Ordering (`$gt`, `$gte`, `$lt`, `$lte`)

PHP's own `>` and `<` order values of any type: `"admin" > 18`, `[] > 18` and `null < 5` are all true. QueryCheck only compares values that can be ordered against each other:

| Value vs operand | Result |
|---|---|
| number vs number | compared numerically (`1` and `1.0` are equal), exactly also beyond 2^53 |
| string vs string | compared as text, byte by byte, like MongoDB: `"10" < "9"`, `"12.0" > "12"`, `"9:00" > "10:00"` |
| boolean vs boolean | `false < true` |
| `null` vs `null` | equal, so `$gte` and `$lte` match |
| number vs numeric string | without strict mode compared numerically (`137 > "136"`, `is_numeric()` decides, so `"1e2"` is 100); in strict mode an error |
| `NaN` vs a number | never greater or less (`NaN >= NaN` is true, as `NaN` equals `NaN`) |
| anything else (`null` or a boolean vs another type, arrays, objects, `"admin"` vs `18`) | does not match (in strict mode an error, except for `null`) |

```php
$qc = new QueryCheck(['age' => ['$gte' => 18]]);
$qc->test(['age' => 30]);      // true
$qc->test(['age' => 'admin']); // false
$qc->test(['age' => []]);      // false

$qc = new QueryCheck(['failedAttempts' => ['$lt' => 5]]);
$qc->test(['failedAttempts' => null]); // false
```

To order numeric strings as numbers, compare them with a number: `{v: {$gt: 9}}` matches `"10"`, `{v: {$gt: "9"}}` does not. String bounds compare as text, so zero-pad times and dates (`"09:00"`, `"2020-09-15"`).

`$not` inverts the result, so `{age: {$not: {$gte: 18}}}` matches `"admin"`. Use QueryCheck as an allow check (`if ($qc->test($doc)) allow();`), not as a deny list.

### Array Fields

As in MongoDB, `$eq`, `$ne` and `$in` on an array field match if the whole array matches or one of its elements does:

| Query | `["user", "admin"]` | `["user"]` | `[]` |
|---|---|---|---|
| `{role: "admin"}` | match | no | no |
| `{role: {$ne: "admin"}}` | no | match | match |
| `{role: {$in: ["admin", "owner"]}}` | match | no | no |
| `{role: {$not: {$in: ["admin"]}}}` | no | match | match |

So `{role: {$ne: "banned"}}` does not match `["banned", "admin"]`. A whole array can be matched too: `{tags: ["a", "b"]}` and `{tags: {$in: [["a", "b"]]}}` match `["a", "b"]`, while `{tags: ["a"]}` does not match `["a", "b"]`. `{v: null}` matches `[null]`.

`$gt`, `$gte`, `$lt` and `$lte` do not look into arrays: an array field never matches them (MongoDB compares each element, see [Differences from MongoDB](#differences-from-mongodb)).

## Strict Mode

Strict mode is off by default. It turns type mismatches into errors:

* **Two non-null values of different types are an error** and throw a `StrictTypeError`: string vs number, array vs number, string vs array, boolean vs number, and so on. Integers and floats are one type, so `12` vs `12.0` is not a mismatch.
* **`null` is "no value", not a wrong type.** It never throws: it does not match `$eq`, `$gt`, `$gte`, `$lt` and `$lte`, and it matches `$ne`. Use the default `UnknownVariableException` (see [Missing Fields](#missing-fields)) if missing data should be an error.
* **Array fields are compared element by element** for `$eq`, `$ne` and `$in`. Arrays may mix types, so an element of another type does not match, but does not throw either.
* **Comparisons of the same type are identical in both modes.** Strict mode only changes what happens when types differ.

Without strict mode, strings and numbers mix (`"137"` equals `137`, `137 > "136"`) and every other mismatch simply does not match. Use strict mode when your documents have reliable types and a wrong type points to bad data or a wrong query; leave it off for loosely typed input such as form fields or CSV.

```php
$qc = new QueryCheck(['age' => '30']);
$qc->setStrictMode(true);
$qc->test(['age' => 30]); // throws StrictTypeError: string vs number
```

| | Not strict | Strict |
|---|---|---|
| `$eq` string vs number (`{v: 137}` on `"137"`) | compared as text, matches | throws `StrictTypeError` |
| `$ne` string vs number (`{v: {$ne: 137}}` on `"137"`) | compared as text, does not match | throws `StrictTypeError` (`$ne` throws exactly when `$eq` would) |
| `$eq` / `$ne` with other mismatched types (`{v: true}` on `1`) | does not match / matches | throws `StrictTypeError` |
| `$eq` / `$ne` with `null` on one side | does not match / matches | does not match / matches |
| `$in` element, array element, `$eq`/`$ne`/`$in` inside `$expr`: string vs number | compared as text, matches | does not match |
| `$gt`, `$gte`, `$lt`, `$lte` with values that cannot be ordered (string vs number, boolean vs another type, arrays, objects) | does not match | throws `StrictTypeError`, also inside `$expr` |
| `$gt`, `$gte`, `$lt`, `$lte` with `null` against another type (`{amount: {$gte: 1000}}` on a missing amount with `setThrowOnUnknownVariable(false)`) | does not match | does not match |
| `$regex` on a non-string | numbers are matched as text, `null`, booleans, arrays and objects do not match | throws `StrictTypeError` (`null` does not match) |
| `$in` operand from the operand evaluator that is not a list (a literal one is a `SyntaxError`) | does not match | throws `StrictTypeError` |
| `test()` with data that is not an object | returns `false` | throws `StrictTypeError` |

## Missing Fields

By default, a field that does not exist in the document throws an `UnknownVariableException`. An existing field with the value `null` is not missing.

`setThrowOnUnknownVariable(false)` makes a missing field compare as `null` instead of throwing. Use it for MongoDB-like queries where `{field: null}` means "null or missing":

```php
$qc = new QueryCheck(['nonExistent' => null]);
$qc->setThrowOnUnknownVariable(false);
$qc->test(['someKey' => 'value']); // true: nonExistent is missing, treated as null
```

A `null` in the middle of a path makes the whole path `null`: `{'profile.age': null}` matches `['profile' => null]`.

## Differences from MongoDB

QueryCheck follows MongoDB's query semantics where that is safe. It deliberately differs in these places:

| Topic | MongoDB | QueryCheck |
|---|---|---|
| String vs number | never equal or ordered (`{v: 137}` does not match `"137"`) | equal and ordered as numbers without strict mode (`"137"` equals `137`, `137 > "136"`); an error in strict mode |
| Ordering across types inside `$expr` | uses the BSON type order: every string is greater than every number, arrays are greater than both, `NaN` is less than every number | values of different types never match (`"admin" >= 18` is false); an error in strict mode. `NaN` is never greater or less than a number |
| `$gt`, `$gte`, `$lt`, `$lte` on array fields | match if one element matches; different conditions may be satisfied by different elements (`{v: {$gt: 10, $lt: 13}}` matches `[5, 20]`) | an array field never matches; an error in strict mode |
| Type errors | never raised; a mismatch simply does not match | strict mode throws `StrictTypeError` for mismatched non-null types |
| Missing fields | behave like `null` (`{v: null}` matches a document without `v`) | throw `UnknownVariableException` by default; `setThrowOnUnknownVariable(false)` gives MongoDB's behaviour |
| Evaluation order | clauses may be reordered by the query planner; short-circuiting is not guaranteed | `$and` and `$or` are evaluated left to right and short-circuit, so an earlier clause can guard a later one |
| Syntax errors | reported by the server | reported by the first `test()`, for the whole query |
| Number types | int32, int64, double and decimal128 | PHP integers and floats (no decimal128); integers are compared exactly with floats |
| Operators | the full query language | the operators listed above; not supported are, for example, `$nin`, `$exists`, `$type`, `$all`, `$elemMatch`, the query operators `$size` and `$mod`, and most aggregation operators |
| `$regex` | PCRE with the options `i`, `m`, `x`, `s` and `u`; matches strings, and the elements of array fields | PCRE through `preg_match()` with the same options; matches strings, and numbers as text without strict mode; array fields never match |

Values of the same type, integer vs float, `NaN`, `null` vs `null`, booleans, `$eq`/`$ne`/`$in` on array fields and text comparison of strings behave as in MongoDB.

## Differences from the JavaScript Version

The JavaScript version 2.0.0 follows this version: the same operators, [Comparison Rules](#comparison-rules), strict mode, missing-field handling, compiled queries and syntax errors. What differs comes from the languages:

* **Numbers:** JavaScript has numbers and BigInts instead of integers and floats; integers beyond 2^53 are compared exactly with BigInts there.
* **Number as text:** `"137"` equals `137` in both, but each language writes numbers as text by its own rules (`1.0E+21` here, `1e+21` in JavaScript).
* **`undefined`:** a field with the value `undefined` counts as missing in JavaScript.
* **Dates:** JavaScript also compares `Date` objects (by time).
* **Regular expressions:** JavaScript uses `RegExp` instead of PCRE (`x` is emulated, there is no backtrack limit).
* **`$trim` with `chars`:** `a..z` is a range here (PHP's `trim()`), not in JavaScript.
* **Own properties:** JavaScript only resolves own properties of the document.

## Advanced Features

### Compiled Queries

The query is compiled into a tree of closures on the first call to `test()` and reused for every later call, so create one `QueryCheck` per query and call `test()` as often as needed. Changing a setting (`setStrictMode()`, `setOperandEvaluator()`, ...) discards the compiled query; it is rebuilt on the next `test()`.

Because the whole query is compiled up front, a syntax error such as an unknown `$operator` throws a `SyntaxError` on the first `test()`, even if it sits in a branch that would not have been evaluated for that document. The message starts with the location of the error:

```
$or[1].age.$not.foo: Unsupported query operator: foo
$expr.$gt[0].$add[1].$size.$nope: Unsupported aggregation operator: $nope
```

To find syntax errors before the first document, call `validate()` after the setters, for example where rules are loaded or saved. It compiles the query and throws the `SyntaxError`; the compiled query is kept, so the next `test()` does not compile again:

```php
$qc = new QueryCheck($rule['condition']);
$qc->setStrictMode(true);
$qc->validate(); // throws SyntaxError for a broken rule
```

Besides unknown operators and the wrong number of arguments, compiling rejects mistakes that would otherwise silently change the result:

| Query | Error |
|---|---|
| `{age: {$gtt: 18}}` | `age: Unsupported query operator: $gtt` (it would be compared as the literal object `{$gtt: 18}` and never match) |
| `{age: {foo: 1, $gt: 18}}` | `age: Cannot mix fields and operators in one object: $gt` |
| `{name: {$options: "i"}}` | `name: $options requires $regex` (it would match every document) |
| `{name: {$regex: "("}}`, `{$options: "e"}`, a pattern that is not a string or ends with a backslash | `name.$regex: Invalid $regex pattern "(": ...` and similar |
| `{age: {$in: 30}}` | `age.$in: $in requires an array, got integer` |
| `{age: {$not: 30}}` | `age.$not: $not requires an operator expression like {$not: {$gt: 5}}` |
| `{age: {$gt: [18]}}` | `age.$gt: $gt cannot compare with an array or object` (it could never match) |
| `{$and: []}`, `{$or: []}` | `$and: $and requires a non-empty array` |
| `{"a..b": 1}`, `{".": 1}`, `"$"` inside `$expr` | `a..b: Invalid field path "a..b": empty segment` (`.` would be the whole document) |
| `{$expr: {gte: [...]}}` | `$expr: $expr requires an operator expression ...` (a plain object counts as true) |
| `{$expr: {$gte: [...], $lt: [...]}}` | `$expr: An operator expression must have exactly one key, found: $gte, $lt` (as in MongoDB; `$lt` would be ignored) |
| `{$divide: ["$a", 0]}` | `$expr.$divide: $divide: division by zero` |

`validate()` cannot find errors that depend on the document, such as a value `$add` cannot add. With an operand evaluator, objects like `{$var: ...}` are custom functions, not unknown operators; parts of `$expr`, `$not` and literal operands are then only checked when a document reaches them and the evaluator returns them unchanged. Errors there come from `test()`, also with the location.

A `$regex` that fails while matching a value, because PCRE's backtrack limit is exhausted or the value is not valid UTF-8 with the `u` option, throws a `RegexError` instead of counting as "no match". Otherwise `$not` would turn the failure into a match.

### Short-circuit Evaluation

`$and` stops at the first branch that does not match and `$or` stops at the first branch that matches, in the order given. Later branches are not evaluated, so they cannot throw, and an operand evaluator is not called for them. This lets an earlier branch guard a later one:

```php
$qc = new QueryCheck([
    '$or' => [
        ['$expr' => ['$eq' => [['$size' => '$items'], 0]]],
        ['items[0].status' => 'paid'],   // only evaluated if items is not empty
    ]
]);
$qc->test(['items' => []]); // true, items[0] is never accessed
```

MongoDB's evaluators short-circuit the same way, but MongoDB does not guarantee it and may reorder branches, so errors in skipped branches can differ.

### Custom Operand Evaluator

You can extend QueryCheck with custom operand evaluation functions. The operand evaluator works with both regular query operators and `$expr` aggregation expressions:

```php
$qc = new QueryCheck([
    'fullName' => ['$concat' => ['$var' => 'firstName'], ' ', ['$var' => 'lastName']]
]);

// Or use with $expr:
$qc = new QueryCheck([
    '$expr' => [
        '$gt' => [
            ['$multiply' => [['$var' => 'price'], ['$var' => 'multiplier']]],
            150
        ]
    ]
]);

$qc->setOperandEvaluator(function($operand, $data) use ($qc) {
    // Custom evaluation logic
    if (is_array($operand) && isset($operand['$var'])) {
        return $qc->getVariableValue($operand['$var'], $data);
    }
    if (is_array($operand) && isset($operand['$concat'])) {
        return implode('', array_map(
            fn($item) => is_string($item) ? $item : $this($item, $data),
            $operand['$concat']
        ));
    }
    return $operand;
});
```

## Testing

Run the test suite locally:

```bash
composer install
./vendor/bin/phpunit
```

Run static analysis:

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

Or with Docker (tests PHP 8.3, 8.4, and 8.5):

```bash
make test        # run tests on all 3 PHP versions
make test-84     # run tests on PHP 8.4 only
make phpstan     # run PHPStan in PHP 8.5 container
make build       # build all Docker images
```

## License

*QueryCheck* is available under the MIT [license](LICENSE).