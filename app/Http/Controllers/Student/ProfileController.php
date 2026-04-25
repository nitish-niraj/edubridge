<?php

namespace App\Http\Controllers\Student;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateProfileRequest;
use App\Http\Resources\StudentProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user()->load('studentProfile');

        return Inertia::render('Student/Profile', [
            'user'    => $user,
            'profile' => $user->studentProfile,
        ]);
    }

    public function update(UpdateProfileRequest $request): StudentProfileResource
    {
        $user    = auth()->user();
        $profile = $user->studentProfile()->firstOrCreate(['user_id' => $user->id]);

        $user->update(['name' => $request->name]);

        if ($request->hasFile('avatar')) {
            $avatarPath = UploadSecurity::storeAvatarWebp($request->file('avatar'), 'public');
            $user->update(['avatar' => '/storage/' . ltrim($avatarPath, '/')]);
        }

        $profile->update([
            'class_grade'        => $request->class_grade,
            'school_name'        => $request->school_name,
            'subjects_needed'    => $request->subjects_needed,
            'preferred_language' => $request->preferred_language,
        ]);

        return (new StudentProfileResource(
            $profile->fresh()->load('user')
        ))->additional([
            'message' => 'Profile updated successfully.',
        ]);
    }
}
