<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherEarningsController extends Controller
{
    /**
     * GET /api/teacher/earnings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isTeacher(), 403);

        $earnings = TeacherEarning::query()
            ->where('teacher_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);

        $summary = [
            'this_month' => (float) TeacherEarning::query()
                ->where('teacher_id', $user->id)
                ->where('status', 'released')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('net_amount'),
            'total' => (float) TeacherEarning::query()
                ->where('teacher_id', $user->id)
                ->where('status', 'released')
                ->sum('net_amount'),
            'pending' => (float) TeacherEarning::query()
                ->where('teacher_id', $user->id)
                ->where('status', 'pending')
                ->sum('net_amount'),
        ];

        return response()->json([
            ...$earnings->toArray(),
            'summary' => $summary,
        ]);
    }
}

