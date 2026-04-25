<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => 'down',
            'cache' => 'down',
            'queue' => 'down',
        ];

        $errors = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'up';
        } catch (\Throwable $exception) {
            $errors['database'] = $exception->getMessage();
        }

        try {
            $key = 'health:cache:' . uniqid('', true);
            Cache::put($key, 'ok', now()->addSeconds(15));
            $checks['cache'] = Cache::get($key) === 'ok' ? 'up' : 'down';
            Cache::forget($key);
        } catch (\Throwable $exception) {
            $errors['cache'] = $exception->getMessage();
        }

        try {
            $queueDriver = (string) config('queue.default');

            if ($queueDriver === 'sync') {
                $checks['queue'] = 'up';
            } else {
                Queue::connection($queueDriver)->size((string) config("queue.connections.{$queueDriver}.queue", 'default'));
                $checks['queue'] = 'up';
            }
        } catch (\Throwable $exception) {
            $errors['queue'] = $exception->getMessage();
        }

        $overall = in_array('down', $checks, true) ? 'degraded' : 'ok';

        return response()->json([
            'status' => $overall,
            'checks' => $checks,
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'version' => env('APP_VERSION', '1.0.0'),
            ],
            'timestamp' => now()->toISOString(),
            'errors' => $errors,
        ], $overall === 'ok' ? 200 : 503);
    }
}
