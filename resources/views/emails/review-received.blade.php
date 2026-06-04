@extends('emails.layout')

@section('title', 'New review received')
@section('heading', 'You received a new review')

@php
    $reviewer = $review->reviewer;
    $rating = number_format((float) ($review->rating ?? 0), 1);
    $snippet = trim((string) ($review->comment ?? ''));
    if (mb_strlen($snippet) > 240) {
        $snippet = mb_substr($snippet, 0, 240) . '…';
    }
@endphp

@section('content')
    <p>Hi {{ $review->reviewee?->name ?? 'there' }},</p>
    <p><strong>{{ $reviewer?->name ?? 'A student' }}</strong> left you a <strong>{{ $rating }}⭐</strong> review{{ $snippet !== '' ? ':' : '.' }}</p>
    @if($snippet !== '')
        <blockquote style="margin: 14px 0; padding: 12px 16px; background: #FFF8F0; border-left: 4px solid #E8553E; border-radius: 8px; color:#2D2D2D; font-family: Nunito, sans-serif;">
            {{ $snippet }}
        </blockquote>
    @endif
    <p>Reviews help your profile stand out and help other students find the right teacher for them.</p>
@endsection
