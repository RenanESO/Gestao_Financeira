<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('installment_number');
            $table->decimal('value', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->unique(['title_id', 'installment_number']);
            $table->index(['title_id', 'status']);
            $table->index(['title_id', 'due_date']);
            $table->index('financial_account_id');
            $table->index(['title_id', 'status', 'due_date'], 'installments_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_installments');
    }
};
