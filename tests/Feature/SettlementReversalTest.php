<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SettlementReversalScenarios;
use Tests\TestCase;

class SettlementReversalTest extends TestCase
{
    use RefreshDatabase, SettlementReversalScenarios;
}
