<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'notifications' => $user ? [
                'unread_count' => (int) AppNotification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->whereNull('dismissed_at')
                    ->count(),
            ] : null,
            'teacher_ui' => $user && $user->isTeacher()
                ? [
                    'high_contrast' => (bool) optional($user->notificationPreferences)->high_contrast,
                ]
                : null,
        ];
    }
}
