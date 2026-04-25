<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_sitemap_endpoint_returns_xml_with_core_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee(route('landing'), false);
        $response->assertSee(route('contact'), false);
        $response->assertSee(route('privacy-policy'), false);
    }

    public function test_robots_file_contains_sitemap_link(): void
    {
        $contents = file_get_contents(public_path('robots.txt'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('User-agent: *', $contents);
        $this->assertStringContainsString('Sitemap: https://yourdomain.com/sitemap.xml', $contents);
    }
}
