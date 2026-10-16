<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinancialOperationsScenarios;
use Tests\TestCase;

class FinancialOperationsTest extends TestCase
{
    use FinancialOperationsScenarios, RefreshDatabase;
}
