<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RouteReferenceIntegrityTest extends TestCase
{
    public function test_named_routes_used_in_resources_js_exist(): void
    {
        $namedRoutes = array_fill_keys(array_keys(app('router')->getRoutes()->getRoutesByName()), true);
        $pattern = "/route\\(\\s*['\"]([^'\"]+)['\"]/";
        $missing = [];

        foreach (File::allFiles(resource_path('js')) as $file) {
            if (! in_array($file->getExtension(), ['vue', 'js'], true)) {
                continue;
            }

            $content = File::get($file->getPathname());
            if ($content === '') {
                continue;
            }

            preg_match_all($pattern, $content, $matches);

            foreach (array_unique($matches[1] ?? []) as $routeName) {
                if (! isset($namedRoutes[$routeName])) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $missing[] = str_replace('\\', '/', $relativePath) . ' => ' . $routeName;
                }
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Missing named routes found in resources/js:\n" . implode("\n", $missing)
        );
    }
}

