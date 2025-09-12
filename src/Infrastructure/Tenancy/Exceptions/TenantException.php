<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions;

use Exception;

class TenantException extends Exception
{
    public static function notFound(string $message = 'Tenant not found'): self
    {
        return new self($message, 404);
    }

    public static function notResolved(string $message = 'Tenant could not be resolved'): self
    {
        return new self($message, 400);
    }

    public static function databaseError(string $message = 'Tenant database error'): self
    {
        return new self($message, 500);
    }

    public static function invalidConfiguration(string $message = 'Invalid tenancy configuration'): self
    {
        return new self($message, 500);
    }
}
