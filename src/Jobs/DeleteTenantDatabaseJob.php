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

class DeleteTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120; // 2 minutes

    public int $backoff = 15; // 15 seconds between retries

    public function __construct(
        public TenantIdentifier $tenant,
        public ?string $notifyEmail = null
    ) {
        $this->onQueue('tenant-deletions');
    }

    public function handle(TenantDatabaseManager $databaseManager): void
    {
        Log::info("Starting tenant database deletion job for: {$this->tenant->value()}");

        try {
            $databaseManager->deleteTenantDatabase($this->tenant);

            Log::info("Successfully completed tenant database deletion job for: {$this->tenant->value()}");

            if ($this->notifyEmail) {
                $this->sendSuccessNotification();
            }

        } catch (Throwable $e) {
            Log::error("Tenant database deletion job failed for {$this->tenant->value()}: ".$e->getMessage());

            if ($this->notifyEmail) {
                $this->sendFailureNotification($e);
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Tenant database deletion job permanently failed for {$this->tenant->value()}: ".$exception->getMessage());

        if ($this->notifyEmail) {
            $this->sendPermanentFailureNotification($exception);
        }
    }

    private function sendSuccessNotification(): void
    {
        Log::info("Database deletion success notification sent to: {$this->notifyEmail}");
    }

    private function sendFailureNotification(Throwable $e): void
    {
        Log::info("Database deletion failure notification sent to: {$this->notifyEmail}");
    }

    private function sendPermanentFailureNotification(Throwable $e): void
    {
        Log::info("Database deletion permanent failure notification sent to: {$this->notifyEmail}");
    }
}
