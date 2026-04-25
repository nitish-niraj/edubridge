<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reporter_id' => $this->reporter_id,
            'reported_user_id' => $this->reported_user_id,
            'reported_message_id' => $this->reported_message_id,
            'reported_review_id' => $this->reported_review_id,
            'booking_id' => $this->booking_id,
            'type' => $this->type,
            'reason' => $this->reason,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'reporter' => $this->relationLoaded('reporter') ? new UserResource($this->reporter) : null,
            'reported_user' => $this->relationLoaded('reportedUser') ? new UserResource($this->reportedUser) : null,
            'resolved_by_admin' => $this->relationLoaded('resolvedByAdmin') ? new UserResource($this->resolvedByAdmin) : null,
            'message' => $this->relationLoaded('message') ? new MessageResource($this->message) : null,
            'review' => $this->relationLoaded('review') ? new ReviewResource($this->review) : null,
            'booking' => $this->relationLoaded('booking') ? [
                'id' => $this->booking->id,
                'status' => $this->booking->status,
                'payment_status' => $this->booking->payment_status,
                'student_id' => $this->booking->student_id,
                'teacher_id' => $this->booking->teacher_id,
                'start_at' => $this->booking->start_at,
                'end_at' => $this->booking->end_at,
            ] : null,
        ];
    }
}
