<?php

namespace LaravelDoctrine\Tenancy\Contracts;

interface TenantIdentifier
{
    public function value(): mixed;

    public function equals(TenantIdentifier $other): bool;

    public function __toString(): string;
}
