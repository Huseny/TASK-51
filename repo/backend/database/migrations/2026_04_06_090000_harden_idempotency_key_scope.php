<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->string('actor_identifier', 191)->nullable()->after('user_id');
            $table->string('canonical_path', 255)->nullable()->after('request_method');
            $table->string('request_hash', 64)->nullable()->after('canonical_path');
        });

        DB::table('idempotency_keys')
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                DB::table('idempotency_keys')
                    ->where('id', $record->id)
                    ->update([
                        'actor_identifier' => 'legacy:'.$record->id,
                        'canonical_path' => $record->request_path,
                        'request_hash' => hash('sha256', json_encode(['legacy_record' => $record->id], JSON_THROW_ON_ERROR)),
                    ]);
        });

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique('idempotency_keys_key_unique');
            $table->unique(['actor_identifier', 'request_method', 'canonical_path', 'key'], 'idempotency_scope_unique');
            $table->index('user_id');
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique('idempotency_scope_unique');
            $table->dropIndex(['user_id']);
            $table->dropIndex(['key']);
            $table->unique('key');
            $table->dropColumn(['user_id', 'actor_identifier', 'canonical_path', 'request_hash']);
        });
    }
};