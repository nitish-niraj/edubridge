<?php

namespace App\Http\Controllers\Api;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateProfileRequest;
use App\Http\Resources\StudentProfileResource;
use Illuminate\Http\JsonResponse;

class StudentProfileController extends Controller
{
    /**
     * PATCH /api/student/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
        ]);

        if ($request->hasFile('avatar')) {
            $avatarPath = UploadSecurity::storeAvatarWebp($request->file('avatar'), 'public');
            $user->update(['avatar' => '/storage/' . ltrim($avatarPath, '/')]);
        }

        $profile = $user->studentProfile()->firstOrCreate(['user_id' => $user->id]);
        $profile->update([
            'class_grade' => $validated['class_grade'] ?? null,
            'school_name' => $validated['school_name'] ?? null,
            'subjects_needed' => $validated['subjects_needed'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? null,
        ]);

        return (new StudentProfileResource($profile->fresh()->load('user')))
            ->additional(['message' => 'Profile updated successfully.'])
            ->response()
            ->setStatusCode(200);
    }
}

