@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">Share Your Feedback</h2>

    <p style="margin:0 0 8px;">
        Hi {{ $booking->student->name }}, your session with <strong>{{ $booking->teacher->name }}</strong> is complete.
    </p>

    <p style="margin:0 0 8px;">
        A short review helps other students choose the right teacher and helps teachers improve their sessions.
    </p>

    <p style="margin:14px 0 0;">
        <a href="{{ config('app.url') . '/reviews/' . $booking->id }}" style="display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 14px; border-radius:999px; text-decoration:none; background:#0d3b66; color:#fff; font-weight:700;">
            Leave a Review
        </a>
    </p>
@endsection
