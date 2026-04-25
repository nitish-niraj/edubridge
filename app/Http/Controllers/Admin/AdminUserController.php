<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSuspendUsersRequest;
use App\Http\Requests\Admin\DestroyUserRequest;
use App\Http\Requests\Admin\ExportUsersRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Jobs\ExportUsersJob;
use App\Mail\AccountSuspendedMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUserController extends Controller
{
    public function index(UserIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = User::query()
            ->withCount(['bookingsAsStudent', 'bookingsAsTeacher'])
            ->with(['teacherProfile:id,user_id,is_verified']);

        if (($role = $validated['role'] ?? null) && $role !== 'all') {
            $query->where('role', $role);
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($status = $validated['status'] ?? null) && $status !== 'all') {
            if ($status === 'verified') {
                $query->where('role', 'teacher')
                    ->whereHas('teacherProfile', fn ($q) => $q->where('is_verified', true));
            } else {
                $query->where('status', $status);
            }
        }

        $users = $query->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return UserResource::collection($users)->response();
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['teacherProfile', 'studentProfile'])
            ->withCount(['bookingsAsStudent', 'bookingsAsTeacher'])
            ->findOrFail($id);

        $timeline = [];
        $timeline[] = ['event' => 'Account created', 'date' => $user->created_at];

        if ($user->email_verified_at) {
            $timeline[] = ['event' => 'Email verified', 'date' => $user->email_verified_at];
        }

        if ($user->teacherProfile?->is_verified) {
            $timeline[] = ['event' => 'Profile verified', 'date' => $user->teacherProfile->updated_at];
        }

        $firstBooking = Booking::where(function ($q) use ($user): void {
            $q->where('student_id', $user->id)
                ->orWhere('teacher_id', $user->id);
        })->oldest()->first();

        if ($firstBooking) {
            $timeline[] = ['event' => 'First booking', 'date' => $firstBooking->created_at];
        }

        $sessionsCount = Booking::where(function ($q) use ($user): void {
            $q->where('student_id', $user->id)
                ->orWhere('teacher_id', $user->id);
        })->count();

        $user->setAttribute('timeline', $timeline);
        $user->setAttribute('sessions', $sessionsCount);

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            'timeline' => $timeline,
            'sessions' => $sessionsCount,
        ]);
    }

    public function suspend(SuspendUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'suspended']);

        Mail::to($user->email)->send(new AccountSuspendedMail($user));

        AuditLogger::log('user.suspended', 'User', $id, ['admin_note' => $request->input('reason', '')]);

        return response()->json(['message' => 'User suspended.']);
    }

    public function activate(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active', 'warnings_count' => 0]);

        AuditLogger::log('user.activated', 'User', $id);

        return response()->json(['message' => 'User activated.']);
    }

    public function destroy(DestroyUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($request->input('confirm_email') !== $user->email) {
            return response()->json(['message' => 'Email confirmation does not match.'], 422);
        }

        AuditLogger::log('user.deleted', 'User', $id, ['email' => $user->email]);
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    public function bulkSuspend(BulkSuspendUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reason = $validated['reason'] ?? '';
        $userIds = collect($validated['user_ids'])
            ->filter(fn ($id) => (int) $id !== (int) auth()->id())
            ->unique()
            ->values();

        $users = User::whereIn('id', $userIds)->get();

        DB::transaction(function () use ($users): void {
            foreach ($users as $user) {
                $user->update(['status' => 'suspended']);
                Mail::to($user->email)->send(new AccountSuspendedMail($user));
            }
        });

        AuditLogger::log('user.bulk_suspended', 'User', auth()->id() ?? 0, [
            'user_ids' => $users->pluck('id')->values()->all(),
            'admin_note' => $reason,
        ]);

        return response()->json([
            'message' => 'Users suspended.',
            'suspended_count' => $users->count(),
        ]);
    }

    public function export(ExportUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = $validated['email'] ?? $request->user()->email;

        ExportUsersJob::dispatch($validated['role'], $email);

        AuditLogger::log('user.export_requested', 'User', auth()->id() ?? 0, [
            'role' => $validated['role'],
            'email' => $email,
        ]);

        return response()->json([
            'message' => 'User export queued.',
        ], 202);
    }

    public function downloadExport(Request $request): StreamedResponse
    {
        $file = (string) $request->query('file', '');

        abort_unless(str_starts_with($file, 'exports/'), 404);
        abort_unless(Storage::disk('local')->exists($file), 404);

        return Storage::disk('local')->download($file);
    }
}
