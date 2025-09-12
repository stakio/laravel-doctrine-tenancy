<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions;

use Exception;

/**
 * Base exception for all tenancy-related errors.
 */
abstract class TenancyException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
