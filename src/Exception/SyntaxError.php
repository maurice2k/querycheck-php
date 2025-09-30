<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when query syntax is invalid
 */
class SyntaxError extends InvalidArgumentException implements ExceptionInterface
{
}