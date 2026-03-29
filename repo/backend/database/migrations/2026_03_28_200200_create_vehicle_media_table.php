<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['vehicle_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_media');
    }
};
