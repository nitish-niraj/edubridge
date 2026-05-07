<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateNotificationPreferencesRequest;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;

class NotificationPreferenceController extends Controller
{
    public function show(): JsonResponse
    {
        $preferences = UserNotificationPreference::firstOrCreate(['user_id' => auth()->id()]);

        return response()->json(['data' => $preferences]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $preferences = UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated()
        );

        return response()->json([
            'message' => 'Notification preferences updated.',
            'data' => $preferences,
        ]);
    }
}
