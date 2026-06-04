<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ProfileStep1Request;
use App\Http\Requests\Teacher\ProfileStep2Request;
use App\Http\Requests\Teacher\ProfileStep3Request;
use App\Http\Requests\Teacher\ProfileStep4Request;
use App\Http\Requests\Teacher\ProfileStep5Request;
use App\Models\BookingSlot;
use App\Models\Review;
use App\Models\TeacherAvailability;
use App\Models\TeacherDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
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
            'onboarding_step'  => max(auth()->user()->teacherProfile->onboarding_step, 1),
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 2]);
    }

    public function saveStep2(ProfileStep2Request $request): RedirectResponse
    {
        // Spec §3.3: "Other" subject is allowed with a short free-text specification (max 50 chars).
        $subjects      = $request->subjects ?? [];
        $subjectOther  = $request->subject_other;
        $includesOther = in_array('Other', (array) $subjects, true);

        auth()->user()->teacherProfile->update([
            'subjects'      => $subjects,
            'subject_other' => $includesOther ? trim((string) $subjectOther) : null,
            'languages'     => $request->languages,
            'onboarding_step' => max(auth()->user()->teacherProfile->onboarding_step, 2),
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 3]);
    }

    public function saveStep3(ProfileStep3Request $request): RedirectResponse
    {
        auth()->user()->teacherProfile->update([
            'is_free'     => $request->is_free,
            'hourly_rate' => $request->is_free ? null : $request->hourly_rate,
            'onboarding_step' => max(auth()->user()->teacherProfile->onboarding_step, 3),
        ]);

        return redirect()->route('teacher.profile.step', ['step' => 4]);
    }

    public function saveStep4(ProfileStep4Request $request): RedirectResponse
    {
        // Spec §3.1: Store availability as JSON in teacher_profiles AND generate booking_slots.
        // availability format from frontend: { "Monday": {"enabled": true, "start": "09:00", "end": "17:00"}, ... }
        $teacher  = auth()->user();
        $profile  = $teacher->teacherProfile;
        $availability = $request->availability;

        $profile->update([
            'availability' => $availability,
            'onboarding_step' => max($profile->onboarding_step, 4),
        ]);

        // Spec §3.1: "System generates booking_slots from this data."
        // Mirror the standalone /teacher/availability store: keep teacher_availability rows
        // in sync, drop future unbooked slots, and regenerate slots for the next 28 days so
        // students can book the teacher immediately after completing step 4.
        $this->syncAvailabilityAndGenerateSlots($teacher->id, $availability);

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
                    ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                    10 * 1024 * 1024
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

        $profile->update(['onboarding_step' => 5]);

        return redirect()->route('teacher.dashboard')
            ->with('status', 'Profile submitted for verification.');
    }

    /**
     * Replace the teacher's recurring availability with the supplied weekly schedule
     * and regenerate 28 days of booking_slots. Booked slots are preserved.
     *
     * @param  array<string, array{enabled?: bool, on?: bool, start?: string, end?: string}>  $availability
     */
    private function syncAvailabilityAndGenerateSlots(int $teacherId, array $availability): void
    {
        $dayMap = [
            'Monday'    => 'monday',
            'Tuesday'   => 'tuesday',
            'Wednesday' => 'wednesday',
            'Thursday'  => 'thursday',
            'Friday'    => 'friday',
            'Saturday'  => 'saturday',
            'Sunday'    => 'sunday',
            // Accept abbreviated / lowercase keys for forward-compat.
            'Mon' => 'monday', 'Tue' => 'tuesday', 'Wed' => 'wednesday', 'Thu' => 'thursday',
            'Fri' => 'friday', 'Sat' => 'saturday', 'Sun' => 'sunday',
            'mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday', 'thu' => 'thursday',
            'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday',
        ];

        $rows = [];
        foreach ($dayMap as $inputKey => $dayOfWeek) {
            $entry = $availability[$inputKey] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $enabled = (bool) ($entry['enabled'] ?? $entry['on'] ?? false);
            $start   = (string) ($entry['start'] ?? '');
            $end     = (string) ($entry['end'] ?? '');

            if (! $enabled || $start === '' || $end === '' || $end <= $start) {
                continue;
            }

            $rows[] = [
                'teacher_id'  => $teacherId,
                'day_of_week' => $dayOfWeek,
                'start_time'  => $start,
                'end_time'    => $end,
                'is_recurring' => true,
                'is_active'   => true,
                'specific_date' => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        // Replace the teacher's recurring availability atomically.
        TeacherAvailability::where('teacher_id', $teacherId)
            ->where('is_recurring', true)
            ->delete();

        if ($rows !== []) {
            TeacherAvailability::insert($rows);
        }

        // Drop future unbooked slots so we don't leak stale times after a schedule change.
        $today = Carbon::today();
        BookingSlot::where('teacher_id', $teacherId)
            ->whereDate('slot_date', '>=', $today->toDateString())
            ->where('is_booked', false)
            ->delete();

        // Regenerate slots for the next 28 days.
        $end = $today->copy()->addDays(28);
        foreach ($rows as $row) {
            $date = $today->copy();
            while ($date->lte($end)) {
                if (strtolower($date->format('l')) === $row['day_of_week']) {
                    BookingSlot::firstOrCreate([
                        'teacher_id' => $row['teacher_id'],
                        'slot_date'  => $date->toDateString(),
                        'start_time' => $row['start_time'],
                    ], [
                        'end_time'         => $row['end_time'],
                        'duration_minutes' => Carbon::parse($row['start_time'])
                            ->diffInMinutes(Carbon::parse($row['end_time'])),
                        'is_booked'        => false,
                    ]);
                }
                $date->addDay();
            }
        }
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
