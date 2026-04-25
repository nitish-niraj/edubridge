<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [
            ['loc' => route('landing'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('teachers.index'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => route('privacy-policy'), 'changefreq' => 'yearly', 'priority' => '0.4'],
            ['loc' => route('terms'), 'changefreq' => 'yearly', 'priority' => '0.4'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('login'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('student.register'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => route('teacher.register'), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
