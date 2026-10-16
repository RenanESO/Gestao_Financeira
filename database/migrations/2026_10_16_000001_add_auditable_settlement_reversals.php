<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->unique()->constrained('title_settlements')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('reversal_date');
            $table->text('reason');
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'reversals_user_key_unique');
            $table->index(['user_id', 'reversal_date']);
        });
        if (DB::getDriverName() === 'sqlite') {
            // Evita reconstruir titles e perder os CHECKs dos enums no SQLite.
            DB::statement('ALTER TABLE titles ADD COLUMN generated_by_settlement_id INTEGER REFERENCES title_settlements(id) ON DELETE RESTRICT');
            Schema::table('titles', fn (Blueprint $table) => $table->unique('generated_by_settlement_id'));
        } else {
            Schema::table('titles', function (Blueprint $table) {
                $table->foreignId('generated_by_settlement_id')->nullable()->unique()
                    ->constrained('title_settlements')->restrictOnDelete();
            });
        }
        Schema::table('receipts', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', fn (Blueprint $table) => $table->dropColumn('voided_at'));
        Schema::table('titles', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['generated_by_settlement_id']);
            }
            $table->dropUnique(['generated_by_settlement_id']);
            $table->dropColumn('generated_by_settlement_id');
        });
        Schema::dropIfExists('settlement_reversals');
    }
};
