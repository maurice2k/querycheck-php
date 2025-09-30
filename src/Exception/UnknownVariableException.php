<?php

declare(strict_types=1);

namespace Maurice2k\QueryCheck\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a variable is not defined
 */
class UnknownVariableException extends InvalidArgumentException implements ExceptionInterface
{
}