<?php

namespace LaravelDoctrine\Tenancy\Domain\ValueObjects;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use Ramsey\Uuid\UuidInterface;

class TenantId implements TenantIdentifier
{
    private UuidInterface $value;

    public function __construct(UuidInterface $value)
    {
        $this->value = $value;
    }

    public function value(): UuidInterface
    {
        return $this->value;
    }

    public function equals(TenantIdentifier $other): bool
    {
        return $this->value->equals($other->value());
    }

    public function __toString(): string
    {
        return $this->value->toString();
    }

    public static function fromString(string $value): self
    {
        return new self(\Ramsey\Uuid\Uuid::fromString($value));
    }
}
