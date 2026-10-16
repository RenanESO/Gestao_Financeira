<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->restrictOnDelete();
            $table->foreignId('title_installment_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('settlement_date');
            $table->decimal('original_value', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('interest', 15, 2)->default(0);
            $table->decimal('fine', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('title_id');
            $table->index('title_installment_id');
            $table->index('settlement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_settlements');
    }
};
