@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">Your EduBridge verification code</h2>

    <p style="margin:0 0 10px;">Hi {{ $user->name }},</p>
    <p style="margin:0 0 12px;">Use the one-time verification code below to continue.</p>

    <div style="margin:0 0 14px; text-align:center; border-radius:10px; border:2px dashed #1d4ed8; background:#eff6ff; padding:16px;">
        <span style="font-family:'Courier New',monospace; font-size:34px; letter-spacing:7px; font-weight:700; color:#1d4ed8;">{{ $otp }}</span>
    </div>

    <p style="margin:0 0 8px;"><strong>This code expires in 15 minutes.</strong></p>
    <p style="margin:0;">If you did not request this, you can safely ignore this message.</p>
@endsection
