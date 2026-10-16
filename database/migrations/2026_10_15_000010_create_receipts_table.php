<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->constrained('title_settlements')->restrictOnDelete();
            $table->string('receipt_number', 20)->unique();
            $table->date('issue_date');
            $table->string('pdf_path');
            $table->timestamps();
            $table->index('user_id');
            $table->unique('settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
