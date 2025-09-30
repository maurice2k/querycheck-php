# QueryCheck for PHP

*QueryCheck* is a logical JSON query evaluator which uses the [MongoDB query style](https://docs.mongodb.com/manual/tutorial/query-documents/).

This is a PHP 8.4+ port of the original [JavaScript/Node.js querycheck package](https://github.com/maurice2k/querycheck).

## Supported Operators

The following comparison operators are supported:
* [$eq](https://docs.mongodb.com/manual/reference/operator/query/eq/)
* [$gt](https://docs.mongodb.com/manual/reference/operator/query/gt/)
* [$gte](https://docs.mongodb.com/manual/reference/operator/query/gte/)
* [$in](https://docs.mongodb.com/manual/reference/operator/query/in/)
* [$lt](https://docs.mongodb.com/manual/reference/operator/query/lt/)
* [$lte](https://docs.mongodb.com/manual/reference/operator/query/lte/)
* [$ne](https://docs.mongodb.com/manual/reference/operator/query/ne/)
* [$regex](https://docs.mongodb.com/manual/reference/operator/query/regex/)

As well as the following logical operators:
* [$and](https://docs.mongodb.com/manual/reference/operator/query/and/)
* [$or](https://docs.mongodb.com/manual/reference/operator/query/or/)
* [$not](https://docs.mongodb.com/manual/reference/operator/query/not/)

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

You can extend QueryCheck with custom operand evaluation functions:

```php
$qc = new QueryCheck([
    'fullName' => ['$concat' => ['$var' => 'firstName'], ' ', ['$var' => 'lastName']]
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