@extends('emails.layout')

@section('content')
    <h1>Dispute update</h1>

    <p>Hello,</p>

    <p>Your EduBridge booking #{{ $booking->id }} has a dispute update:</p>

    <p><strong>{{ $resolution }}</strong></p>

    @if($refundAmount > 0)
        <p>Refund amount: ₹{{ number_format($refundAmount, 2) }}</p>
    @endif

    <p>Thank you for helping keep EduBridge safe and fair.</p>
@endsection
