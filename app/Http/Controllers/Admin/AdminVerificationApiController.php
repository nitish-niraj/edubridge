<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;

class AdminVerificationApiController extends Controller
{
    /**
     * GET /api/admin/verifications
     */
    public function index(): JsonResponse
    {
        $teachers = TeacherProfile::query()
            ->with(['user:id,name,email,avatar,status', 'documents'])
            ->whereHas('user', fn ($q) => $q->where('role', 'teacher'))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json($teachers);
    }
}

