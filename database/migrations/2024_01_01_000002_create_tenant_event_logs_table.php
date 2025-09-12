<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_event_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('event_type', 100);
            $table->string('status', 50);
            $table->json('metadata')->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'event_type']);
            $table->index(['status', 'occurred_at']);
            $table->index(['event_type', 'status']);
            $table->index('tenant_id');
            $table->index('event_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_event_logs');
    }
};
