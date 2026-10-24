<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Contatos e titulos ja possuem deleted_at; aqui so os demais cadastros recebem exclusao logica.
    private const TABLES = ['categories', 'financial_accounts', 'payment_types'];

    public function up(): void
    {
        foreach (self::TABLES as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Reverter remove a marcacao: registros excluidos logicamente voltam a ficar visiveis.
        foreach (self::TABLES as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
