<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_dependency_statuses(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertTrue(in_array($response->getStatusCode(), [200, 503], true));
        $response->assertJsonStructure([
            'status',
            'checks' => ['database', 'cache', 'queue'],
            'app' => ['name', 'env', 'version'],
            'timestamp',
            'errors',
        ]);

        $response->assertJsonPath('checks.database', 'up');
        $response->assertJsonPath('checks.cache', 'up');
    }
}
