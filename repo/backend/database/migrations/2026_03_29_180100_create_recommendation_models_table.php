<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_models', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version');
            $table->boolean('is_active')->default(false);
            $table->json('feature_snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['is_active', 'version']);
            $table->unique('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_models');
    }
};
