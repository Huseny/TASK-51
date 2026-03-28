<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_order_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ride_order_id')->constrained('ride_orders')->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('triggered_by', 50);
            $table->string('trigger_reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ride_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_order_audit_logs');
    }
};
