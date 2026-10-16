<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\ReceiptNotificationScenarios;
use Tests\TestCase;

class MySqlReceiptNotificationTest extends TestCase
{
    use ReceiptNotificationScenarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS nome')->nome);
        $this->assertSame(0, DB::transactionLevel());
        $this->bootArtifacts();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupArtifacts();
        } finally {
            parent::tearDown();
        }
    }
}
