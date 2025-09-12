<?php

namespace LaravelDoctrine\Tenancy\Domain\ValueObjects;

class TenantName
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException('Tenant name cannot be empty');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Tenant name cannot exceed 255 characters');
        }

        $this->value = trim($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
