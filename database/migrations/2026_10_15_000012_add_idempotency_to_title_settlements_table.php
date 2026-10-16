<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('title_settlements', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique(['title_id', 'idempotency_key'], 'settlements_title_id_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('title_settlements', function (Blueprint $table) {
            $table->dropUnique('settlements_title_id_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
