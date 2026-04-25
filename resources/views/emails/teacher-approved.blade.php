@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">Your Account Has Been Approved</h2>

    <p style="margin:0 0 10px;">Hi {{ $user->name }},</p>
    <p style="margin:0 0 10px;">Your EduBridge teacher account has been verified and approved by our admin team.</p>
    <p style="margin:0 0 10px;">You can now complete your profile and start accepting students.</p>
@endsection
