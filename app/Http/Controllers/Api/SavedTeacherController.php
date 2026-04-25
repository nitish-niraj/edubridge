<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SavedTeacherIndexRequest;
use App\Http\Requests\Api\SavedTeacherToggleRequest;
use App\Http\Resources\TeacherCardResource;
use App\Models\SavedTeacher;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SavedTeacherController extends Controller
{
    public function index(SavedTeacherIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 12);
        $studentId = $request->user()->id;

        $savedTeacherIds = SavedTeacher::query()
            ->where('student_id', $studentId)
            ->select('teacher_id');

        $teachers = TeacherProfile::query()
            ->with('user:id,name,avatar,status')
            ->whereIn('user_id', $savedTeacherIds)
            ->where('is_verified', true)
            ->orderByDesc('rating_avg')
            ->orderByDesc('total_reviews')
            ->addSelect('teacher_profiles.*')
            ->addSelect(['is_saved' => SavedTeacher::query()
                ->selectRaw('1')
                ->whereColumn('saved_teachers.teacher_id', 'teacher_profiles.user_id')
                ->where('saved_teachers.student_id', $studentId)
                ->limit(1),
            ])
            ->paginate($perPage)
            ->withQueryString();

        return TeacherCardResource::collection($teachers);
    }

    public function store(SavedTeacherToggleRequest $request, int $teacher_id): JsonResponse
    {
        $validated = $request->validated();
        $studentId = $request->user()->id;

        SavedTeacher::query()->firstOrCreate([
            'student_id' => $studentId,
            'teacher_id' => (int) $validated['teacher_id'],
        ]);

        return response()->json([
            'saved' => true,
            'message' => 'Teacher saved successfully.',
        ], 201);
    }

    public function destroy(SavedTeacherToggleRequest $request, int $teacher_id): JsonResponse
    {
        $validated = $request->validated();
        $studentId = $request->user()->id;

        SavedTeacher::query()
            ->where('student_id', $studentId)
            ->where('teacher_id', (int) $validated['teacher_id'])
            ->delete();

        return response()->json([
            'saved' => false,
            'message' => 'Teacher removed from saved list.',
        ]);
    }
}
