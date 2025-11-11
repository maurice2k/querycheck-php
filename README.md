# QueryCheck for PHP

*QueryCheck* is a logical JSON query evaluator which uses the [MongoDB query style](https://docs.mongodb.com/manual/tutorial/query-documents/).

This is a PHP 8.3+ port of the original [JavaScript/Node.js querycheck package](https://github.com/maurice2k/querycheck).

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

**Conditional Operators:**
* [$cond](https://www.mongodb.com/docs/manual/reference/operator/aggregation/cond/) - Conditional expression with if/then/else branches

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
* `$expr` can be combined with `$and` and `$or` operators to mix regular field queries with aggregation expressions.
* The aggregation `$not` operator evaluates `false`, `null`, and `0` as false; all other values (including non-zero numbers and arrays) as true.
* The aggregation `$in` operator checks if a value exists in an array, similar to the query `$in` but used within expressions.

## PHP vs JavaScript Differences

### Undefined Values

In JavaScript, accessing a non-existent property returns `undefined`. In PHP, there is no `undefined` type.

To handle this difference, the PHP version:
- Undefined in PHP means a key doesn't exist in an array (checked with `!isset()` or `!array_key_exists()`)
- When a key doesn't exist, it's treated as `null` internally, but the comparison logic distinguishes between actual `null` values and non-existent keys
- By default, non-existent keys (undefined) do NOT equal `null`
- You can use `setUndefinedEqualsNull(true)` to make undefined values equal to `null`

```php
$qc = new QueryCheck(['nonExistent' => null]);
$qc->setUndefinedEqualsNull(true);
$qc->test(['someKey' => 'value']); // returns true - nonExistent is undefined, treated as null
```

### Arrays vs Objects

PHP uses arrays for both lists and associative arrays (objects). The package automatically detects:
- Sequential numeric arrays (list-style) are treated as arrays
- Associative arrays are treated as objects/hashes

## Advanced Features

### Strict Mode

Enable strict type checking:

```php
$qc = new QueryCheck(['age' => '30']);
$qc->setStrictMode(true);
$qc->test(['age' => 30]); // throws StrictTypeError - strict type mismatch
```

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

Run the test suite:

```bash
composer install
./vendor/bin/phpunit
```

## License

*QueryCheck* is available under the MIT [license](LICENSE).