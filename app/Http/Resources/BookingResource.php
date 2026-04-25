<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'teacher_id' => $this->teacher_id,
            'slot_id' => $this->slot_id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'status' => $this->status,
            'session_type' => $this->session_type,
            'subject' => $this->subject,
            'notes' => $this->notes,
            'price' => $this->price,
            'platform_fee' => $this->platform_fee,
            'teacher_payout' => $this->teacher_payout,
            'payment_status' => $this->payment_status,
            'conversation_id' => $this->when(isset($this->conversation_id), $this->conversation_id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'student' => $this->relationLoaded('student') ? new UserResource($this->student) : null,
            'teacher' => $this->relationLoaded('teacher') ? new UserResource($this->teacher) : null,
            'payment' => $this->relationLoaded('payment') ? [
                'id' => $this->payment->id,
                'status' => $this->payment->status,
                'amount' => $this->payment->amount,
                'amount_paise' => $this->payment->amount_paise,
                'platform_fee' => $this->payment->platform_fee,
                'teacher_payout' => $this->payment->teacher_payout,
                'paid_at' => $this->payment->paid_at,
                'released_at' => $this->payment->released_at,
            ] : null,
            'review' => $this->relationLoaded('review') ? new ReviewResource($this->review) : null,
            'reports_count' => (int) ($this->reports_count ?? 0),
            'reports' => $this->relationLoaded('reports') ? ReportResource::collection($this->reports) : null,
            'events' => $this->relationLoaded('events') ? BookingEventResource::collection($this->events) : null,
            'video_session' => $this->relationLoaded('videoSession') ? [
                'id' => $this->videoSession->id,
                'started_at' => $this->videoSession->started_at,
                'ended_at' => $this->videoSession->ended_at,
                'duration_minutes' => $this->videoSession->duration_minutes,
                'recording_url' => $this->videoSession->recording_url,
            ] : null,
            'slot' => $this->relationLoaded('slot') ? [
                'id' => $this->slot->id,
                'slot_date' => $this->slot->slot_date,
                'start_time' => $this->slot->start_time,
                'end_time' => $this->slot->end_time,
                'duration_minutes' => $this->slot->duration_minutes,
                'is_booked' => (bool) $this->slot->is_booked,
            ] : null,
        ];
    }
}
