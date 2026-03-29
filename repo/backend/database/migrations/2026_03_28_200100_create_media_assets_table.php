<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('original_filename', 255);
            $table->string('mime_type', 50);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256_hash', 64)->unique();
            $table->string('disk_path', 500);
            $table->string('compressed_path', 500)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
