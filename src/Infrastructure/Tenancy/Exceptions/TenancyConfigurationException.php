<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions;

class TenancyConfigurationException extends TenancyException
{
    public static function invalidEntityClass(string $class): self
    {
        return new self("Invalid entity class: {$class}", 500);
    }

    public static function missingConfiguration(string $key): self
    {
        return new self("Missing required configuration: {$key}", 500);
    }

    public static function invalidRouting(string $entity): self
    {
        return new self("Entity '{$entity}' is not properly configured in entity routing", 500);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self($message, 500);
    }
}
