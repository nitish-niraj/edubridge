@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">New EduBridge Feedback</h2>
    <p style="margin:0 0 8px;"><strong>Type:</strong> {{ $feedback->type }}</p>
    <p style="margin:0 0 8px;"><strong>Page:</strong> {{ $feedback->page_url }}</p>
    <p style="margin:0 0 8px;"><strong>User ID:</strong> {{ $feedback->user_id ?? 'Guest' }}</p>
    <p style="margin:0 0 8px;"><strong>Description:</strong></p>
    <div style="margin-top:8px; padding:12px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0;">
        {!! nl2br(e($feedback->description)) !!}
    </div>
    @if($feedback->screenshot_path)
        <p style="margin:10px 0 0;"><strong>Screenshot:</strong> {{ $feedback->screenshot_path }}</p>
    @endif
@endsection
