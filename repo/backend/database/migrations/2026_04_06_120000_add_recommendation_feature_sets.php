<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_feature_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recommendation_model_id')->constrained('recommendation_models')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedBigInteger('seed');
            $table->timestamp('created_at')->useCurrent();

            $table->unique('version');
            $table->index(
                ['recommendation_model_id', 'version'],
                'rec_feat_sets_model_ver_idx'
            );
        });

        Schema::create('recommendation_feature_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_set_id')->constrained('recommendation_feature_sets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('feature_key', 120);
            $table->json('feature_value');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feature_set_id', 'feature_key']);
            $table->index(['feature_set_id', 'user_id']);
            $table->index(['feature_set_id', 'item_id']);
        });

        Schema::table('recommendation_results', function (Blueprint $table): void {
            $table->foreignId('feature_set_id')->nullable()->after('model_version_id')
                ->constrained('recommendation_feature_sets')->nullOnDelete();
            $table->index(
                ['feature_set_id', 'user_id', 'rank_order'],
                'rec_results_feat_user_rank_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_results', function (Blueprint $table): void {
            $table->dropIndex('rec_results_feat_user_rank_idx');
            $table->dropConstrainedForeignId('feature_set_id');
        });

        Schema::dropIfExists('recommendation_feature_values');
        Schema::dropIfExists('recommendation_feature_sets');
    }
};
