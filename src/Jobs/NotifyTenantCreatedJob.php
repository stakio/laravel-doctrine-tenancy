<?php

namespace LaravelDoctrine\Tenancy\Jobs;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyTenantCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $tenantId,
        private string $eventId
    ) {}

    public function handle(): void
    {
        $tenantId = TenantId::fromString($this->tenantId);
        
        TenancyLogger::tenantResolved($tenantId, 'tenant_created_notification_sent', [
            'event_id' => $this->eventId
        ]);

        // Here you can add custom notification logic
        // For example: send email, webhook, etc.
    }
}
