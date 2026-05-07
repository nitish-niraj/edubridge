<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min(50, (int) $request->query('limit', 20)));
        $criticalOnly = $request->boolean('critical_only');
        $activeOnly = $request->boolean('active_only');

        $query = AppNotification::query()
            ->where('user_id', $user->id)
            ->when($criticalOnly, fn ($builder) => $builder->where('is_critical', true))
            ->when($activeOnly, fn ($builder) => $builder->whereNull('dismissed_at'))
            ->orderByDesc('id');

        return response()->json([
            'data' => $query->limit($limit)->get(),
            'unread_count' => (int) AppNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->whereNull('dismissed_at')
                ->count(),
        ]);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);

        return response()->json(['message' => 'Notification dismissed.']);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
