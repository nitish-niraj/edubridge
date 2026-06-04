@extends('emails.layout')

@section('title', 'Earnings released')
@section('heading', 'Your earnings have been released')

@php
    $teacher = $booking->teacher;
    $amount = number_format((float) ($booking->teacher_payout ?? 0), 2);
@endphp

@section('content')
    <p>Hi {{ $teacher?->name ?? 'Teacher' }},</p>
    <p>Great news — <strong>INR {{ $amount }}</strong> from your session with {{ $booking->student?->name ?? 'a student' }} on {{ optional($booking->start_at)->format('d M Y, h:i A') }} has been added to your earnings.</p>
    <p>You can view your full earnings and payout history anytime from your Teacher dashboard.</p>
@endsection
