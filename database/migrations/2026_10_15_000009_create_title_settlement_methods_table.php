<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_settlement_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('title_settlements')->restrictOnDelete();
            $table->foreignId('payment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 15, 2);
            $table->timestamps();
            $table->index('settlement_id');
            $table->index('financial_account_id');
            $table->index('payment_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_settlement_methods');
    }
};
