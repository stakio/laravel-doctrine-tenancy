<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions;

class TenantResolutionException extends TenancyException
{
    public static function invalidHeader(string $headerValue): self
    {
        return new self("Invalid tenant header value: {$headerValue}", 400);
    }

    public static function invalidUuid(string $uuid): self
    {
        return new self("Invalid UUID format: {$uuid}", 400);
    }

    public static function domainNotFound(string $domain): self
    {
        return new self("No tenant found for domain: {$domain}", 404);
    }

    public static function resolutionFailed(string $reason = 'Unknown reason'): self
    {
        return new self("Tenant resolution failed: {$reason}", 400);
    }
}
