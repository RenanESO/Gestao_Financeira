<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        $maior = 0;
        foreach (DB::table('receipts')->pluck('receipt_number') as $numero) {
            if (preg_match('/^RCB-[0-9]{4}-([0-9]{1,11})$/iD', $numero, $partes)) {
                $maior = max($maior, (int) $partes[1]);
            }
        }
        DB::table('document_sequences')->insert(['name' => 'receipts', 'last_number' => $maior]);
        Schema::table('receipts', function (Blueprint $table) {
            $table->json('snapshot')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->char('deduplication_key', 64)->nullable();
            $table->unique(['user_id', 'deduplication_key'], 'notifications_user_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_dedup_unique');
            $table->dropColumn('deduplication_key');
        });
        Schema::table('receipts', fn (Blueprint $table) => $table->dropColumn(['snapshot', 'pdf_sha256']));
        Schema::dropIfExists('document_sequences');
    }
};
