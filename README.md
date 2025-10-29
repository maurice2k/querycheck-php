# QueryCheck for PHP

*QueryCheck* is a logical JSON query evaluator which uses the [MongoDB query style](https://docs.mongodb.com/manual/tutorial/query-documents/).

This is a PHP 8.3+ port of the original [JavaScript/Node.js querycheck package](https://github.com/maurice2k/querycheck).

## Supported Operators

### Comparison Operators
* [$eq](https://docs.mongodb.com/manual/reference/operator/query/eq/)
* [$gt](https://docs.mongodb.com/manual/reference/operator/query/gt/)
* [$gte](https://docs.mongodb.com/manual/reference/operator/query/gte/)
* [$in](https://docs.mongodb.com/manual/reference/operator/query/in/)
* [$lt](https://docs.mongodb.com/manual/reference/operator/query/lt/)
* [$lte](https://docs.mongodb.com/manual/reference/operator/query/lte/)
* [$ne](https://docs.mongodb.com/manual/reference/operator/query/ne/)
* [$regex](https://docs.mongodb.com/manual/reference/operator/query/regex/)

### Logical Operators
* [$and](https://docs.mongodb.com/manual/reference/operator/query/and/)
* [$or](https://docs.mongodb.com/manual/reference/operator/query/or/)
* [$not](https://docs.mongodb.com/manual/reference/operator/query/not/)

### Aggregation Expressions (`$expr`)
* [$expr](https://www.mongodb.com/docs/manual/reference/operator/query/expr/) - Allows aggregation expressions within query predicates

The following operators can be used within `$expr`:

**Arithmetic Operators:**
* `$add` - Adds numbers together
* `$subtract` - Subtracts two numbers
* `$multiply` - Multiplies numbers together
* `$divide` - Divides two numbers
* `$mod` - Returns the modulo of two numbers

**Comparison Operators** (for aggregation context):
* `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte` - Same as query operators but for aggregation expressions

**Conditional Operators:**
* `$cond` - Conditional expression with if/then/else branches

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
```

**Notes:**
* Field references in `$expr` require the `$` prefix (e.g., `$fieldName`). Without the prefix, values are treated as literals.
* `$expr` can be combined with `$and` and `$or` operators to mix regular field queries with aggregation expressions.

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