<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectTeacherRequest;
use App\Http\Requests\Admin\VerificationIndexRequest;
use App\Mail\TeacherApprovedMail;
use App\Mail\TeacherRejectedMail;
use App\Models\TeacherDocument;
use App\Models\TeacherProfile;
use App\Http\Resources\TeacherDocumentViewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function index(VerificationIndexRequest $request): Response
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = TeacherProfile::with(['user', 'documents'])
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'));

        if ($status === 'pending') {
            $query->where('is_verified', false)
                ->whereDoesntHave('documents', fn ($q) => $q->where('status', 'rejected'));
        } elseif ($status === 'approved') {
            $query->where('is_verified', true);
        } elseif ($status === 'rejected') {
            $query->where('is_verified', false)
                ->whereHas('documents', fn ($q) => $q->where('status', 'rejected'));
        }

        if ($search !== '') {
            $query->whereHas('user', function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $teachers = $query->latest()
            ->get()
            ->map(function (TeacherProfile $profile): array {
                $user = $profile->user;

                $rejectedDocs = $profile->documents->where('status', 'rejected')->count();
                $pendingDocs = $profile->documents->where('status', 'pending')->count();

                if ((bool) $profile->is_verified) {
                    $verificationStatus = 'approved';
                } elseif ($rejectedDocs > 0 && $pendingDocs === 0) {
                    $verificationStatus = 'rejected';
                } else {
                    $verificationStatus = 'pending';
                }

                return [
                    'id' => $profile->id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'phone' => $user?->phone,
                    'avatar' => $user?->avatar,
                    'registered_at' => optional($user?->created_at)->toDateString(),
                    'status' => $user?->status,
                    'bio' => $profile->bio,
                    'experience_years' => $profile->experience_years,
                    'previous_school' => $profile->previous_school,
                    'subjects' => $profile->subjects ?? [],
                    'languages' => $profile->languages ?? [],
                    'hourly_rate' => $profile->hourly_rate,
                    'is_free' => (bool) $profile->is_free,
                    'verification_status' => $verificationStatus,
                    'documents_count' => $profile->documents->count(),
                    'documents' => $profile->documents->map(function (TeacherDocument $document): array {
                        $disk = $this->resolveDocumentDisk();
                        $fileSize = null;
                        if (Storage::disk($disk)->exists($document->file_path)) {
                            $fileSize = Storage::disk($disk)->size($document->file_path);
                        }

                        return [
                            'id' => $document->id,
                            'type' => $document->type,
                            'status' => $document->status,
                            'original_filename' => $document->original_filename,
                            'file_size' => $fileSize,
                            'signed_url' => $this->makeDocumentUrl($document),
                        ];
                    })->values()->all(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Verifications', [
            'teachers' => $teachers,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $profile = TeacherProfile::with('user')->findOrFail($id);

        $hasAnyDocument = $profile->documents()->exists();
        if (! $hasAnyDocument) {
            return response()->json([
                'message' => 'No verification documents are uploaded yet for this teacher profile.',
            ], 422);
        }

        $profile->update(['is_verified' => true]);
        $profile->user?->update(['status' => 'active']);
        $profile->documents()->whereIn('status', ['pending', 'rejected'])->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        try {
            Mail::to($profile->user->email)->send(new TeacherApprovedMail($profile->user));

            return response()->json(['message' => 'Teacher approved successfully and approval email sent.']);
        } catch (\Throwable $exception) {
            Log::warning('Teacher approved but approval email failed to send.', [
                'teacher_profile_id' => $profile->id,
                'user_id' => $profile->user?->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Teacher approved successfully, but approval email could not be sent right now.']);
        }
    }

    public function reject(RejectTeacherRequest $request, int $id): JsonResponse
    {
        $profile = TeacherProfile::with('user')->findOrFail($id);

        $profile->update(['is_verified' => false]);
        $profile->user?->update(['status' => 'active']);

        $profile->documents()->whereIn('status', ['pending', 'approved'])->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        try {
            Mail::to($profile->user->email)->send(new TeacherRejectedMail($profile->user, $request->reason));

            return response()->json(['message' => 'Teacher application rejected and rejection email sent.']);
        } catch (\Throwable $exception) {
            Log::warning('Teacher application rejected but rejection email failed to send.', [
                'teacher_profile_id' => $profile->id,
                'user_id' => $profile->user?->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Teacher application rejected, but rejection email could not be sent right now.']);
        }
    }

    public function showDocument(int $document): StreamedResponse
    {
        $teacherDocument = TeacherDocument::with('teacherProfile.user')->findOrFail($document);
        abort_unless($teacherDocument->teacherProfile?->user?->role === 'teacher', 404);
        $disk = $this->resolveDocumentDisk();

        abort_unless(Storage::disk($disk)->exists($teacherDocument->file_path), 404);

        return Storage::disk($disk)->download(
            $teacherDocument->file_path,
            $teacherDocument->original_filename
        );
    }

    public function viewDocument(int $document): JsonResponse
    {
        $teacherDocument = TeacherDocument::with('teacherProfile.user')->findOrFail($document);
        abort_unless($teacherDocument->teacherProfile?->user?->role === 'teacher', 404);

        $expiresAt = now()->addMinutes(5);
        $url = URL::temporarySignedRoute(
            'admin.documents.show',
            $expiresAt,
            ['document' => $teacherDocument->id]
        );

        return TeacherDocumentViewResource::make([
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ])->response();
    }

    private function makeDocumentUrl(TeacherDocument $document): string
    {
        return URL::temporarySignedRoute(
            'admin.documents.show',
            now()->addMinutes(5),
            ['document' => $document->id]
        );
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
