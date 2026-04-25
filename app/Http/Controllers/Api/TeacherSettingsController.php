<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTeacherPreferencesRequest;
use App\Http\Requests\Api\UpdateTeacherProfileFlagsRequest;
use App\Http\Resources\TeacherPreferencesResource;
use App\Http\Resources\TeacherProfileFlagsResource;
use App\Models\TeacherProfile;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;

class TeacherSettingsController extends Controller
{
    public function updatePreferences(UpdateTeacherPreferencesRequest $request): JsonResponse
    {
        $preferences = UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['high_contrast' => $request->boolean('high_contrast')]
        );

        return (new TeacherPreferencesResource($preferences))
            ->additional(['message' => 'Preferences updated.'])
            ->response();
    }

    public function updateProfile(UpdateTeacherProfileFlagsRequest $request): JsonResponse
    {
        $profile = TeacherProfile::query()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $profile->update([
            'tour_completed' => $request->boolean('tour_completed'),
        ]);

        return (new TeacherProfileFlagsResource($profile->fresh()))
            ->additional(['message' => 'Profile flags updated.'])
            ->response();
    }
}
