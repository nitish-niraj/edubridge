<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /**
     * Create a new class group.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:150',
            'subject'      => 'required|string|max:100',
            'description'  => 'nullable|string|max:2000',
            'max_students' => 'nullable|integer|min:2|max:100',
        ]);

        $user = $request->user();

        $conversation = DB::transaction(function () use ($request, $user) {
            $conversation = Conversation::create([
                'created_by'   => $user->id,
                'title'        => $request->input('name'),
                'is_group'     => true,
                'subject'      => $request->input('subject'),
                'description'  => $request->input('description'),
                'max_students' => $request->input('max_students', 30),
                'teacher_id'   => $user->id,
                'invite_code'  => Conversation::generateInviteCode(),
            ]);

            // Add teacher as participant in conversation_participants
            $conversation->participants()->attach($user->id, ['joined_at' => now()]);

            // Add teacher as class member
            ClassMember::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'role'            => 'teacher',
                'joined_at'       => now(),
            ]);

            return $conversation;
        });

        return response()->json([
            'conversation' => $conversation,
            'invite_code'  => $conversation->invite_code,
            'invite_link'  => config('app.url') . '/join/' . $conversation->invite_code,
        ], 201);
    }

    /**
     * List teacher's class groups.
     */
    public function index(Request $request): JsonResponse
    {
        $groups = Conversation::where('teacher_id', $request->user()->id)
            ->where('is_group', true)
            ->withCount(['activeClassMembers as student_count' => function ($q) {
                $q->where('role', 'student');
            }])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($groups);
    }

    /**
     * Show group details.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $conversation = Conversation::with([
            'teacher:id,name,avatar',
            'activeClassMembers.user:id,name,email,avatar',
        ])->findOrFail($id);

        return response()->json($conversation);
    }

    /**
     * Preview group via invite code (public).
     */
    public function preview(string $inviteCode): JsonResponse
    {
        $conversation = Conversation::where('invite_code', $inviteCode)
            ->where('is_group', true)
            ->with('teacher:id,name,avatar')
            ->withCount(['activeClassMembers as student_count' => function ($q) {
                $q->where('role', 'student');
            }])
            ->firstOrFail();

        return response()->json([
            'id'            => $conversation->id,
            'name'          => $conversation->title,
            'subject'       => $conversation->subject,
            'description'   => $conversation->description,
            'teacher'       => $conversation->teacher,
            'student_count' => $conversation->student_count,
            'max_students'  => $conversation->max_students,
        ]);
    }

    /**
     * Student joins a group via invite code.
     */
    public function join(Request $request, string $inviteCode): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where('invite_code', $inviteCode)
            ->where('is_group', true)
            ->firstOrFail();

        // Already a member?
        $existing = ClassMember::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You are already a member of this class.'], 422);
        }

        // Enforce max limit
        $currentCount = $conversation->studentCount();
        if ($currentCount >= $conversation->max_students) {
            return response()->json(['message' => 'This class is full.'], 422);
        }

        DB::transaction(function () use ($conversation, $user) {
            $conversation->participants()->syncWithoutDetaching([
                $user->id => ['joined_at' => now()],
            ]);

            ClassMember::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'role'            => 'student',
                'joined_at'       => now(),
            ]);
        });

        return response()->json([
            'message'       => 'Successfully joined ' . $conversation->title,
            'conversation'  => $conversation,
        ]);
    }

    /**
     * Teacher adds student by email.
     */
    public function addMember(Request $request, int $groupId): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $conversation = Conversation::findOrFail($groupId);

        if ($conversation->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the class teacher can add members.'], 403);
        }

        $student = User::where('email', $request->input('email'))
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json(['message' => 'No student found with that email.'], 404);
        }

        $existing = ClassMember::where('conversation_id', $groupId)
            ->where('user_id', $student->id)
            ->whereNull('left_at')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Student is already in this class.'], 422);
        }

        if ($conversation->studentCount() >= $conversation->max_students) {
            return response()->json(['message' => 'This class is full.'], 422);
        }

        DB::transaction(function () use ($conversation, $student) {
            $conversation->participants()->syncWithoutDetaching([
                $student->id => ['joined_at' => now()],
            ]);

            ClassMember::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $student->id,
                'role'            => 'student',
                'joined_at'       => now(),
            ]);
        });

        return response()->json([
            'message' => $student->name . ' added to ' . $conversation->title,
            'student' => $student->only(['id', 'name', 'email', 'avatar']),
        ]);
    }

    /**
     * Teacher removes a student from the group.
     */
    public function removeMember(Request $request, int $groupId, int $userId): JsonResponse
    {
        $conversation = Conversation::findOrFail($groupId);

        if ($conversation->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the class teacher can remove members.'], 403);
        }

        $member = ClassMember::where('conversation_id', $groupId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Student not found in this class.'], 404);
        }

        $member->update(['left_at' => now()]);

        // Remove from conversation participants
        $conversation->participants()->updateExistingPivot($userId, ['left_at' => now()]);

        return response()->json(['message' => 'Student removed from class.']);
    }

    /**
     * Teacher mutes/unmutes a student in the group.
     */
    public function toggleMute(Request $request, int $groupId, int $userId): JsonResponse
    {
        $conversation = Conversation::findOrFail($groupId);

        if ($conversation->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the teacher can mute/unmute.'], 403);
        }

        $member = ClassMember::where('conversation_id', $groupId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $member->update(['is_muted' => ! $member->is_muted]);

        return response()->json([
            'is_muted' => $member->is_muted,
            'message'  => $member->is_muted ? 'Student muted.' : 'Student unmuted.',
        ]);
    }

    /**
     * Teacher grants/revokes draw permission.
     */
    public function toggleDraw(Request $request, int $groupId, int $userId): JsonResponse
    {
        $conversation = Conversation::findOrFail($groupId);

        if ($conversation->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the teacher can manage draw permissions.'], 403);
        }

        $member = ClassMember::where('conversation_id', $groupId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $member->update(['can_draw' => ! $member->can_draw]);

        // Broadcast to all participants
        broadcast(new \App\Events\DrawPermissionGranted(
            $groupId,
            $userId,
            $member->can_draw
        ));

        return response()->json([
            'can_draw' => $member->can_draw,
            'message'  => $member->can_draw ? 'Draw permission granted.' : 'Draw permission revoked.',
        ]);
    }
}
