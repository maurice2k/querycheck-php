<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Exception;

use TypeError;

/**
 * Exception thrown when strict type checking fails
 */
class StrictTypeError extends TypeError implements ExceptionInterface
{
}