<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->enum('type', ['client', 'supplier', 'both']);
            $table->string('name');
            $table->string('document', 18)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip_code', 9)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'active']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
