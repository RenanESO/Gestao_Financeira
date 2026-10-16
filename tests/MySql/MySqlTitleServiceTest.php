<?php

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\TitleServiceScenarios;
use Tests\TestCase;

class MySqlTitleServiceTest extends TestCase
{
    use TitleServiceScenarios;

    private bool $transacaoAberta = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS nome')->nome);
        DB::beginTransaction();
        $this->transacaoAberta = true;
    }

    protected function tearDown(): void
    {
        if ($this->transacaoAberta) {
            DB::rollBack();
        }
        parent::tearDown();
    }
}
