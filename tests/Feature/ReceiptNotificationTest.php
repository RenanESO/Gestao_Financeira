<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\ReceiptNotificationScenarios;
use Tests\TestCase;

class ReceiptNotificationTest extends TestCase
{
    use DatabaseMigrations, ReceiptNotificationScenarios;

    protected function setUp(): void
    {
        parent::setUp();
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
