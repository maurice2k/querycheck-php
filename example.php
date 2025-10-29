<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Maurice2k\QueryCheck\QueryCheck;
use Maurice2k\QueryCheck\Exception\UnknownVariableException;

// Example 1: Opening hours check
echo "Example 1: Opening Hours Check\n";
echo "================================\n\n";

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
    echo "✓ We're OPEN!\n\n";
} else {
    echo "✗ Sorry, we're CLOSED!\n\n";
}

// Example 2: User eligibility check
echo "Example 2: User Eligibility Check\n";
echo "==================================\n\n";

$user = [
    'age' => 25,
    'country' => 'US',
    'verified' => true,
    'memberSince' => '2020-01-15',
];

$eligibilityQuery = new QueryCheck([
    '$and' => [
        ['age' => ['$gte' => 18]],
        ['country' => ['$in' => ['US', 'CA', 'UK']]],
        ['verified' => true],
    ]
]);

if ($eligibilityQuery->test($user)) {
    echo "✓ User is eligible\n\n";
} else {
    echo "✗ User is not eligible\n\n";
}

// Example 3: Complex product filtering
echo "Example 3: Product Filtering\n";
echo "=============================\n\n";

$products = [
    [
        'name' => 'Laptop',
        'price' => 999,
        'category' => 'electronics',
        'inStock' => true,
        'tags' => ['premium', 'bestseller'],
    ],
    [
        'name' => 'Mouse',
        'price' => 29,
        'category' => 'electronics',
        'inStock' => true,
        'tags' => ['budget'],
    ],
    [
        'name' => 'Desk',
        'price' => 299,
        'category' => 'furniture',
        'inStock' => false,
        'tags' => [],
    ],
];

// Find electronics under $1000 that are in stock
$productFilter = new QueryCheck([
    'category' => 'electronics',
    'price' => ['$lt' => 1000],
    'inStock' => true,
]);

echo "Products matching criteria:\n";
foreach ($products as $product) {
    if ($productFilter->test($product)) {
        echo "  - {$product['name']} (\${$product['price']})\n";
    }
}
echo "\n";

// Example 4: Regex pattern matching
echo "Example 4: Regex Pattern Matching\n";
echo "==================================\n\n";

$document = [
    'email' => 'user@example.com',
    'phone' => '+1-555-0123',
];

$emailValidator = new QueryCheck([
    'email' => [
        '$regex' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
    ]
]);

if ($emailValidator->test($document)) {
    echo "✓ Valid email format\n\n";
} else {
    echo "✗ Invalid email format\n\n";
}

// Example 5: Nested object access
echo "Example 5: Nested Object Access\n";
echo "================================\n\n";

$order = [
    'id' => 'ORD-001',
    'customer' => [
        'name' => 'John Doe',
        'address' => [
            'city' => 'New York',
            'country' => 'US',
        ],
    ],
    'items' => [
        ['sku' => 'A123', 'quantity' => 2],
        ['sku' => 'B456', 'quantity' => 1],
    ],
];

$usOrderCheck = new QueryCheck([
    'customer.address.country' => 'US',
    'items[0].quantity' => ['$gte' => 1],
]);

if ($usOrderCheck->test($order)) {
    echo "✓ Order is from US with valid items\n\n";
} else {
    echo "✗ Order conditions not met\n\n";
}

// Example 6: Handling undefined values
echo "Example 6: Undefined Values\n";
echo "===========================\n\n";

$profile = [
    'name' => 'Alice',
    'email' => null,
];

// Without undefinedEqualsNull - throws exception
$query1 = new QueryCheck(['phone' => null]);
echo "Query for undefined 'phone' field (default behavior): ";
try {
    echo $query1->test($profile) ? "Match\n" : "No match\n";
} catch (UnknownVariableException $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// With undefinedEqualsNull
$query2 = new QueryCheck(['phone' => null]);
$query2->setUndefinedEqualsNull(true);
echo "Query for undefined 'phone' field (undefinedEqualsNull=true): ";
echo $query2->test($profile) ? "Match\n" : "No match\n";

// Null vs undefined
$query3 = new QueryCheck(['email' => null]);
echo "Query for null 'email' field: ";
echo $query3->test($profile) ? "Match\n" : "No match\n";

echo "\n";

// Example 7: $expr with aggregation expressions
echo "Example 7: \$expr with Aggregation Expressions\n";
echo "===============================================\n\n";

// Budget tracking - compare two fields
$monthlyBudget = [
    'category' => 'food',
    'budget' => 400,
    'spent' => 450,
];

$overBudgetQuery = new QueryCheck([
    '$expr' => [
        '$gt' => ['$spent', '$budget']
    ]
]);

echo "Budget check (spent > budget): ";
if ($overBudgetQuery->test($monthlyBudget)) {
    echo "⚠️  Over budget!\n";
} else {
    echo "✓ Within budget\n";
}
echo "\n";

// Calculate total from multiple fields
$invoice = [
    'subtotal' => 100,
    'tax' => 15,
    'shipping' => 10,
];

$totalQuery = new QueryCheck([
    '$expr' => [
        '$gte' => [
            ['$add' => ['$subtotal', '$tax', '$shipping']],
            125
        ]
    ]
]);

echo "Invoice total check (subtotal + tax + shipping >= 125): ";
echo $totalQuery->test($invoice) ? "✓ Meets minimum\n" : "✗ Below minimum\n";
echo "\n";

// Conditional discount calculation
$supplies = [
    'item' => 'notebook',
    'qty' => 200,
    'price' => 8,
];

// Discount: 50% if qty >= 100, otherwise 25%
$discountQuery = new QueryCheck([
    '$expr' => [
        '$lt' => [
            [
                '$cond' => [
                    'if' => ['$gte' => ['$qty', 100]],
                    'then' => ['$multiply' => ['$price', 0.5]],
                    'else' => ['$multiply' => ['$price', 0.75]]
                ]
            ],
            5
        ]
    ]
]);

echo "Discounted price check (with conditional discount < 5): ";
echo $discountQuery->test($supplies) ? "✓ Eligible for promotion\n" : "✗ Not eligible\n";
echo "\n";

// Complex calculation
$dimensions = [
    'length' => 10,
    'width' => 5,
    'height' => 3,
];

// Check if volume (length * width * height) is greater than 100
$volumeQuery = new QueryCheck([
    '$expr' => [
        '$gt' => [
            ['$multiply' => ['$length', '$width', '$height']],
            100
        ]
    ]
]);

echo "Volume check (length * width * height > 100): ";
echo $volumeQuery->test($dimensions) ? "✓ Large package\n" : "✗ Small package\n";
echo "\n";