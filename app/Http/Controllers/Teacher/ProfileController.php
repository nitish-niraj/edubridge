<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ProfileStep1Request;
use App\Http\Requests\Teacher\ProfileStep2Request;
use App\Http\Requests\Teacher\ProfileStep3Request;
use App\Http\Requests\Teacher\ProfileStep4Request;
use App\Http\Requests\Teacher\ProfileStep5Request;
use App\Models\Review;
use App\Models\TeacherDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function showProfile(): Response
    {
        $user    = auth()->user();
        $profile = $user->teacherProfile;

        $latestReviews = Review::query()
            ->where('reviewee_id', $user->id)
            ->where('is_visible', true)
            ->whereNotNull('comment')
            ->with(['reviewer:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Review $review): array => [
                'id' => $review->id,
                'rating' => (float) $review->rating,
                'student_name' => $review->reviewer?->name,
                'comment' => $review->comment,
                'date' => optional($review->created_at)->toDateString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Teacher/MyProfile', [
            'profile' => $profile,
            'user'    => $user->only('name', 'email', 'avatar'),
            'latest_reviews' => $latestReviews,
        ]);
    }

    public function showStep(int $step): Response
    {
        $user    = auth()->user();
        $profile = $user->teacherProfile;

        return Inertia::render("Teacher/ProfileStep{$step}", [
            'profile' => $profile,
            'step'    => $step,
            'user'    => $user->only('name', 'email'),
        ]);
    }

    public function saveStep1(ProfileStep1Request $request): RedirectResponse
    {
        auth()->user()->teacherProfile->update([
            'bio'              => $request->bio,
            'experience_years' => $request->experience_years,
            'previous_school'  => $request->previous_school,
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 2]);
    }

    public function saveStep2(ProfileStep2Request $request): RedirectResponse
    {
        auth()->user()->teacherProfile->update([
            'subjects'  => $request->subjects,
            'languages' => $request->languages,
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 3]);
    }

    public function saveStep3(ProfileStep3Request $request): RedirectResponse
    {
        auth()->user()->teacherProfile->update([
            'is_free'     => $request->is_free,
            'hourly_rate' => $request->is_free ? null : $request->hourly_rate,
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 4]);
    }

    public function saveStep4(ProfileStep4Request $request): RedirectResponse
    {
        // Store availability as JSON in teacher_profiles
        // availability format: { "mon": {"on": true, "start": "09:00", "end": "17:00"}, ... }
        auth()->user()->teacherProfile->update([
            'availability' => $request->availability,
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 5]);
    }

    public function saveStep5(ProfileStep5Request $request): RedirectResponse
    {
        $user    = auth()->user();
        $profile = $user->teacherProfile;

        if ($request->hasFile('avatar')) {
            $avatarPath = UploadSecurity::storeAvatarWebp($request->file('avatar'), 'public');
            $user->update(['avatar' => '/storage/' . ltrim($avatarPath, '/')]);
        }

        $documentDisk  = $this->resolveDocumentDisk();
        $documentTypes = ['degree', 'service_record', 'id_proof'];
        foreach ($documentTypes as $type) {
            if ($request->hasFile($type)) {
                $file = $request->file($type);
                $path = UploadSecurity::storeValidatedFile(
                    $file,
                    $documentDisk,
                    'teacher-documents/' . $profile->id,
                    $type,
                    ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
                );

                TeacherDocument::updateOrCreate(
                    ['teacher_id' => $profile->id, 'type' => $type],
                    [
                        'file_path'         => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'status'            => 'pending',
                        'rejection_reason'  => null,
                        'reviewed_by'       => null,
                        'reviewed_at'       => null,
                        'uploaded_at'       => now(),
                    ]
                );
            }
        }

        return redirect()->route('teacher.dashboard')
            ->with('status', 'Profile submitted for verification.');
    }

    private function resolveDocumentDisk(): string
    {
        $s3 = config('filesystems.disks.s3');
        $hasS3Config = ! empty($s3['key'] ?? null)
            && ! empty($s3['secret'] ?? null)
            && ! empty($s3['bucket'] ?? null);

        return $hasS3Config ? 's3' : 'local';
    }
}
