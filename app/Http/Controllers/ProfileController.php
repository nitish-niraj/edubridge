<?php

namespace App\Http\Controllers;

use App\Helpers\UploadSecurity;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): RedirectResponse
    {
        return Redirect::route($this->accountRouteName($request));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $saveUser = false;

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
                $saveUser = true;
            }
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = UploadSecurity::storeAvatarWebp($request->file('avatar'), 'public');
            $user->avatar = '/storage/' . ltrim($avatarPath, '/');
            $saveUser = true;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $saveUser = true;
        }

        if ($saveUser) {
            $user->save();
        }

        $profilePayload = [];
        if ($user->isStudent()) {
            $profilePayload = array_intersect_key($validated, array_flip([
                'class_grade',
                'school_name',
                'subjects_needed',
                'preferred_language',
            ]));

            if ($profilePayload !== []) {
                $user->studentProfile()->firstOrCreate(['user_id' => $user->id])->update($profilePayload);
            }
        }

        if ($user->isTeacher()) {
            $profilePayload = array_intersect_key($validated, array_flip([
                'bio',
                'experience_years',
                'previous_school',
            ]));

            if ($profilePayload !== []) {
                $user->teacherProfile()->firstOrCreate(['user_id' => $user->id])->update($profilePayload);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                ],
            ]);
        }

        return Redirect::route($this->accountRouteName($request))
            ->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function accountRouteName(Request $request): string
    {
        $role = $request->user()?->role;

        return match ($role) {
            'teacher' => 'teacher.settings',
            'admin' => 'admin.settings.account',
            default => 'student.settings',
        };
    }
}
