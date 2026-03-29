<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('products')->cascadeOnDelete();
            $table->enum('interaction_type', ['view', 'purchase']);
            $table->float('score');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'item_id', 'interaction_type'], 'user_interactions_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
