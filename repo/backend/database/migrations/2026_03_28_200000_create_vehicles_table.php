<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('make', 100);
            $table->string('model', 100);
            $table->year('year');
            $table->string('license_plate', 20);
            $table->string('color', 50)->nullable();
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
