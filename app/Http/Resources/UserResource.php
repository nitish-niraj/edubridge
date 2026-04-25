<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $currentUser = $request?->user();
        $isAdmin = (bool) ($currentUser?->isAdmin() ?? false);
        $isSelf = $currentUser && (int) $currentUser->id === (int) $this->id;

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'role'                  => $this->role,
            'avatar'                => $this->avatar,
            'status'                => $this->status,
            'email_verified_at'     => $this->email_verified_at,
            'created_at'            => $this->created_at,
            'bookings_as_student_count' => $this->when(isset($this->bookings_as_student_count), (int) $this->bookings_as_student_count),
            'bookings_as_teacher_count' => $this->when(isset($this->bookings_as_teacher_count), (int) $this->bookings_as_teacher_count),
            'warnings_count'        => $this->when($isAdmin || $isSelf, (int) ($this->warnings_count ?? 0)),
            'last_login_ip'         => $this->when($isAdmin, $this->last_login_ip ?? null),
            'teacher_verified'      => $this->whenLoaded('teacherProfile', (bool) ($this->teacherProfile?->is_verified ?? false)),
            'student_visibility'    => $this->when($isAdmin && $this->role === 'teacher', $this->resolveStudentVisibility()),
            'timeline'              => $this->when(isset($this->timeline), $this->timeline),
            'sessions'              => $this->when(isset($this->sessions), $this->sessions),
        ];
    }

    private function resolveStudentVisibility(): ?array
    {
        if (! $this->relationLoaded('teacherProfile')) {
            return null;
        }

        $reasons = [];
        $profile = $this->teacherProfile;

        if ($this->status !== 'active') {
            $reasons[] = [
                'code' => 'account_not_active',
                'message' => 'Account status must be active.',
            ];
        }

        if (! $profile) {
            $reasons[] = [
                'code' => 'profile_missing',
                'message' => 'Teacher profile record is missing.',
            ];
        } elseif (! (bool) $profile->is_verified) {
            $reasons[] = [
                'code' => 'profile_not_verified',
                'message' => 'Teacher profile is not verified yet.',
            ];
        }

        return [
            'is_visible_to_students' => $reasons === [],
            'reasons' => $reasons,
        ];
    }
}
