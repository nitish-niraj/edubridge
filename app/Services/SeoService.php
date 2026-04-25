<?php

namespace App\Services;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SeoService
{
    public function page(array $overrides = []): array
    {
        $defaults = [
            'title' => 'EduBridge | Learn from verified teachers online',
            'description' => 'EduBridge connects students and families with verified teachers for secure and engaging online classes.',
            'keywords' => [
                'online tutoring',
                'verified teachers',
                'student learning platform',
                'EduBridge',
            ],
            'image' => '/images/og-cover.jpg',
            'type' => 'website',
            'url' => url('/'),
            'site_name' => config('app.name', 'EduBridge'),
            'twitter_card' => 'summary_large_image',
        ];

        $meta = array_merge($defaults, $overrides);

        if (is_string($meta['keywords'] ?? null)) {
            $meta['keywords'] = array_values(array_filter(array_map('trim', explode(',', $meta['keywords']))));
        }

        $meta['keywords'] = array_values(array_filter((array) ($meta['keywords'] ?? [])));
        $meta['image'] = $this->toAbsoluteUrl((string) $meta['image']);
        $meta['url'] = $this->toAbsoluteUrl((string) $meta['url']);

        return $meta;
    }

    public function render(array $meta): HtmlString
    {
        $keywords = implode(', ', $meta['keywords'] ?? []);

        $tags = [
            '<title>' . e($meta['title']) . '</title>',
            '<meta name="description" content="' . e($meta['description']) . '">',
            '<meta name="keywords" content="' . e($keywords) . '">',
            '<meta property="og:type" content="' . e($meta['type'] ?? 'website') . '">',
            '<meta property="og:title" content="' . e($meta['title']) . '">',
            '<meta property="og:description" content="' . e($meta['description']) . '">',
            '<meta property="og:url" content="' . e($meta['url']) . '">',
            '<meta property="og:image" content="' . e($meta['image']) . '">',
            '<meta property="og:site_name" content="' . e($meta['site_name'] ?? config('app.name', 'EduBridge')) . '">',
            '<meta name="twitter:card" content="' . e($meta['twitter_card'] ?? 'summary_large_image') . '">',
            '<meta name="twitter:title" content="' . e($meta['title']) . '">',
            '<meta name="twitter:description" content="' . e($meta['description']) . '">',
            '<meta name="twitter:image" content="' . e($meta['image']) . '">',
            '<link rel="canonical" href="' . e($meta['url']) . '">',
        ];

        return new HtmlString(implode("\n", $tags));
    }

    private function toAbsoluteUrl(string $value): string
    {
        if ($value === '') {
            return url('/');
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return url($value);
    }
}
