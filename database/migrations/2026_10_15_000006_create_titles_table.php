<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_title_id')->nullable()->constrained('titles')->restrictOnDelete();
            $table->string('title_number', 20);
            $table->enum('title_type', ['receivable', 'payable']);
            $table->string('description');
            $table->string('document_number', 50)->nullable();
            $table->decimal('original_value', 15, 2);
            $table->unsignedInteger('installments_count')->default(1);
            $table->unsignedInteger('current_installment')->default(1);
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled', 'partial'])->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['monthly', 'yearly'])->nullable();
            $table->unsignedInteger('recurrence_day')->nullable();
            $table->unsignedInteger('recurrence_month')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'title_number']);
            $table->index(['user_id', 'title_type', 'status']);
            $table->index(['user_id', 'due_date']);
            $table->index('contact_id');
            $table->index('category_id');
            $table->index('financial_account_id');
            $table->index('parent_title_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
