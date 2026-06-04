@extends('emails.layout')

@section('title', 'Group session started')
@section('heading', 'A group session is starting now')

@section('content')
    <p>Hi {{ $user->name ?? 'there' }},</p>
    <p><strong>{{ $teacherName }}</strong> just started a group session. Jump in to join the conversation and the live video room.</p>
    <p style="margin: 24px 0;">
        <a href="{{ url('/student/chat') }}" style="background: #E8553E; color: #fff; padding: 12px 22px; border-radius: 999px; text-decoration: none; font-weight: 700;">
            Open Chat
        </a>
    </p>
    <p>If the button above does not work, paste this URL into your browser:<br><span style="color:#6B7280;">{{ url('/student/chat') }}</span></p>
@endsection
