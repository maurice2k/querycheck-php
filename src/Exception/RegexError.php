<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Exception;

use RuntimeException;

/**
 * Exception thrown when a $regex fails while matching a value, for example
 * when PCRE's backtrack limit is exhausted or the value is not valid UTF-8
 * for the "u" option. Thrown instead of treating the failure as "no match",
 * so that $not cannot turn it into a match.
 */
class RegexError extends RuntimeException implements ExceptionInterface
{
}
