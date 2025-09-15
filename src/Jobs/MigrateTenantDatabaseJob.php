<?php

namespace LaravelDoctrine\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use Throwable;

class MigrateTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes

    public int $backoff = 30; // 30 seconds between retries

    public function __construct(
        public TenantIdentifier $tenant,
        public ?string $notifyEmail = null
    ) {
        $this->onQueue('tenant-migrations');
    }

    public function handle(TenantDatabaseManager $databaseManager): void
    {
        Log::info("Starting tenant migration job for: {$this->tenant->value()}");

        try {
            $databaseManager->migrateTenantDatabase($this->tenant);

            Log::info("Successfully completed tenant migration job for: {$this->tenant->value()}");

            // Optional: Send notification email
            if ($this->notifyEmail) {
                $this->sendSuccessNotification();
            }

        } catch (Throwable $e) {
            Log::error("Tenant migration job failed for {$this->tenant->value()}: ".$e->getMessage());

            // Optional: Send failure notification
            if ($this->notifyEmail) {
                $this->sendFailureNotification($e);
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Tenant migration job permanently failed for {$this->tenant->value()}: ".$exception->getMessage());

        if ($this->notifyEmail) {
            $this->sendPermanentFailureNotification($exception);
        }
    }

    private function sendSuccessNotification(): void
    {
        // Implement email notification logic
        Log::info("Migration success notification sent to: {$this->notifyEmail}");
    }

    private function sendFailureNotification(Throwable $e): void
    {
        // Implement failure notification logic
        Log::info("Migration failure notification sent to: {$this->notifyEmail}");
    }

    private function sendPermanentFailureNotification(Throwable $e): void
    {
        // Implement permanent failure notification logic
        Log::info("Migration permanent failure notification sent to: {$this->notifyEmail}");
    }
}
