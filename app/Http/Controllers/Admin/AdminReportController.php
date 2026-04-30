<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportActionRequest;
use App\Http\Requests\Admin\ReportIndexRequest;
use App\Http\Resources\ReportResource;
use App\Mail\AccountSuspendedMail;
use App\Mail\UserWarningMail;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Services\ReviewRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AdminReportController extends Controller
{
    public function index(ReportIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Report::with([
            'reporter:id,name,avatar',
            'reportedUser:id,name,avatar',
            'resolvedByAdmin:id,name,avatar',
            'booking.student:id,name,avatar',
            'booking.teacher:id,name,avatar',
            'review.reviewer:id,name,avatar',
            'review.reviewee:id,name,avatar',
            'message.sender:id,name,avatar',
        ]);

        if (($status = $validated['status'] ?? null) && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('reason', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('reportedUser', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $reports = $query->orderByDesc('created_at')->paginate(20);

        return ReportResource::collection($reports)->response();
    }

    public function show(int $id): JsonResponse
    {
        $report = Report::with([
            'reporter:id,name,avatar',
            'reportedUser:id,name,avatar',
            'resolvedByAdmin:id,name,avatar',
            'booking.student:id,name,avatar',
            'booking.teacher:id,name,avatar',
            'review.reviewer:id,name,avatar',
            'review.reviewee:id,name,avatar',
            'message.sender:id,name,avatar',
        ])->findOrFail($id);

        return response()->json((new ReportResource($report))->resolve());
    }

    public function warn(ReportActionRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        if ($report->status !== 'pending') {
            return response()->json(['message' => 'This report is already resolved.'], 422);
        }

        if (! $report->reported_user_id) {
            return response()->json(['message' => 'No reported user linked to this report.'], 422);
        }

        $user = User::findOrFail($report->reported_user_id);
        $user->increment('warnings_count');

        Mail::to($user->email)->send(new UserWarningMail($user, $report->reason));

        if ($user->warnings_count >= 3) {
            $user->update(['status' => 'suspended']);
            Mail::to($user->email)->send(new AccountSuspendedMail($user));
        }

        $report->update([
            'status'      => 'action_taken',
            'admin_notes' => $request->input('note'),
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        AuditLogger::log('report.warned', 'Report', $id, ['user_id' => $user->id, 'warnings' => $user->warnings_count]);

        return response()->json(['message' => 'Warning sent.', 'warnings_count' => $user->warnings_count]);
    }

    public function removeContent(int $id, ReviewRatingService $ratings): JsonResponse
    {
        $report = Report::with(['review'])->findOrFail($id);

        if ($report->status !== 'pending') {
            return response()->json(['message' => 'This report is already resolved.'], 422);
        }

        if ($report->reported_message_id) {
            Message::where('id', $report->reported_message_id)->delete();
        }

        if ($report->review) {
            $report->review->update(['is_visible' => false]);
            $ratings->recalculateForTeacher($report->review->reviewee_id);
        }

        $report->update([
            'status'      => 'action_taken',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        AuditLogger::log('report.content_removed', 'Report', $id);

        return response()->json(['message' => 'Content removed.']);
    }

    public function suspendUser(ReportActionRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        if ($report->status !== 'pending') {
            return response()->json(['message' => 'This report is already resolved.'], 422);
        }

        if (! $report->reported_user_id) {
            return response()->json(['message' => 'No reported user linked to this report.'], 422);
        }

        $user = User::findOrFail($report->reported_user_id);

        $user->update(['status' => 'suspended']);
        Mail::to($user->email)->send(new AccountSuspendedMail($user));

        $report->update([
            'status'      => 'action_taken',
            'admin_notes' => $request->input('note', 'Suspended via report'),
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        AuditLogger::log('report.user_suspended', 'Report', $id, ['user_id' => $user->id]);

        return response()->json(['message' => 'User suspended.']);
    }

    public function dismiss(ReportActionRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        if ($report->status !== 'pending') {
            return response()->json(['message' => 'This report is already resolved.'], 422);
        }

        $report->update([
            'status'      => 'dismissed',
            'admin_notes' => $request->input('note'),
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        AuditLogger::log('report.dismissed', 'Report', $id);

        return response()->json(['message' => 'Report dismissed.']);
    }
}
