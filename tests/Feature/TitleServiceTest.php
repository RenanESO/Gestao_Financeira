<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TitleServiceScenarios;
use Tests\TestCase;

class TitleServiceTest extends TestCase
{
    use RefreshDatabase, TitleServiceScenarios;
}
