<?php

namespace LaravelDoctrine\Tenancy\Domain\ValueObjects;

class Domain
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException('Domain cannot be empty');
        }

        if (!filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Domain cannot exceed 255 characters');
        }

        $this->value = strtolower(trim($value));
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
