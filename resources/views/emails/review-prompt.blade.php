@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">How was your session?</h2>

	<p style="margin:0 0 10px;">Your session with <strong>{{ $booking->teacher->name }}</strong> has been completed.</p>
	<p style="margin:0 0 12px;">Your review helps other students find great teachers.</p>

	<a href="{{ config('app.url') . '/reviews/' . $booking->id }}" style="display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 14px; border-radius:999px; text-decoration:none; background:#16a34a; color:#fff; font-weight:700;">
		Leave a Review
	</a>
@endsection
