@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">New contact form submission</h2>

    <p style="margin:0 0 8px;"><strong>Name:</strong> {{ $submission->name }}</p>
    <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $submission->email }}</p>
    <p style="margin:0 0 8px;"><strong>Subject:</strong> {{ $submission->subject ?: 'N/A' }}</p>
    <p style="margin:0 0 8px;"><strong>User ID:</strong> {{ $submission->user_id ?: 'Guest' }}</p>
    <p style="margin:0 0 8px;"><strong>IP:</strong> {{ $submission->ip_address ?: 'N/A' }}</p>

    <div style="margin-top:14px; padding:14px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0;">
        {!! nl2br(e($submission->message)) !!}
    </div>
@endsection
