<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('purchase_limit_per_user_per_day')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
