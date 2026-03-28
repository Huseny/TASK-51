<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rider_id')->constrained('users');
            $table->foreignId('driver_id')->nullable()->constrained('users');
            $table->text('origin_address');
            $table->text('destination_address');
            $table->unsignedTinyInteger('rider_count');
            $table->dateTime('time_window_start');
            $table->dateTime('time_window_end');
            $table->text('notes')->nullable();
            $table->enum('status', ['created', 'matching', 'accepted', 'in_progress', 'completed', 'canceled', 'exception'])->default('created');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('rider_id');
            $table->index('driver_id');
            $table->index('status');
            $table->index('time_window_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_orders');
    }
};
