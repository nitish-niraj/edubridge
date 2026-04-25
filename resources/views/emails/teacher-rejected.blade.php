@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 12px; color:#0f172a;">Application Not Approved</h2>

    <p style="margin:0 0 10px;">Hi {{ $user->name }},</p>
    <p style="margin:0 0 10px;">After reviewing your application and submitted documents, we were unable to approve your teacher account at this time.</p>

    <div style="margin:10px 0; padding:12px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca;">
        <strong>Reason:</strong> {{ $reason }}
    </div>

    <p style="margin:0;">You can contact support to clarify next steps or re-apply with updated documents.</p>
@endsection
