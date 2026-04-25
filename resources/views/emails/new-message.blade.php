@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">You have a new message</h2>

    <p style="margin:0 0 8px;">
        <strong>{{ $senderName }}</strong> sent you a message:
    </p>

    <div style="margin:0 0 12px; padding:12px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0;">
        {{ $preview }}
    </div>

    <a href="{{ url('/chat/' . $conversationId) }}" style="display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 14px; border-radius:999px; text-decoration:none; background:#0d3b66; color:#fff; font-weight:700;">
        Reply Now
    </a>
@endsection
