<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_version_id')->constrained('recommendation_models')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('products')->cascadeOnDelete();
            $table->float('score');
            $table->unsignedInteger('rank_order');
            $table->boolean('is_exploration')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['model_version_id', 'user_id', 'rank_order'],
                'rec_results_model_user_rank_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_results');
    }
};
