<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_domain_migrations_can_be_reversed_and_reapplied(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('receipts'));
        foreach (['categories', 'financial_accounts', 'payment_types'] as $tabela) {
            $this->assertTrue(Schema::hasColumn($tabela, 'deleted_at'), $tabela);
        }

        $this->artisan('migrate:rollback', ['--step' => 15, '--force' => true])->assertExitCode(0);

        foreach (['categories', 'financial_accounts', 'contacts', 'payment_types', 'titles',
            'title_installments', 'title_settlements', 'title_settlement_methods', 'settlement_reversals', 'receipts', 'notifications', 'document_sequences'] as $tabela) {
            $this->assertFalse(Schema::hasTable($tabela), $tabela);
        }
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'theme'));

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasColumn('users', 'theme'));

        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('users'));
    }
}
