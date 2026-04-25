<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_custom_404_page_renders_for_missing_web_route(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertSee('Page Not Found');
    }

    public function test_api_not_found_returns_json_payload(): void
    {
        $response = $this->getJson('/api/no-such-endpoint');

        $response->assertStatus(404);
        $response->assertJsonPath('status', 404);
        $response->assertJsonPath('message', 'Resource not found.');
    }

    public function test_custom_419_page_renders(): void
    {
        $path = '/__error-expired-test-' . uniqid();
        Route::get($path, fn () => abort(419));

        $response = $this->get($path);

        $response->assertStatus(419);
        $response->assertSee('Session Expired');
    }

    public function test_custom_429_page_renders_when_rate_limit_is_hit(): void
    {
        $path = '/__error-throttle-test-' . uniqid();
        Route::middleware('throttle:1,1')->get($path, fn () => 'ok');

        $this->get($path)->assertOk();
        $response = $this->get($path);

        $response->assertStatus(429);
        $response->assertSee('Too Many Requests');
    }
}
