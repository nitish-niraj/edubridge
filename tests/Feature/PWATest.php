<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PWATest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_served_with_expected_json_payload(): void
    {
        $this->assertFileExists(public_path('manifest.json'));

        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertEquals('EduBridge', $manifest['name']);
        $this->assertEquals('EduBridge', $manifest['short_name']);
        $this->assertEquals('/', $manifest['start_url']);
    }

    public function test_service_worker_is_accessible(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }
}
