@extends('layouts.public')

@section('content')
    @php($supportEmail = config('services.contact.to', config('mail.from.address', 'support@edubridge.local')))

    <article style="max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d9e6f2; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(16, 42, 67, .08);">
        <h1 style="font-family: 'Fraunces', serif; font-size: clamp(2rem, 4vw, 2.8rem); margin-top: 0; color: #102a43;">Contact EduBridge</h1>
        <p style="line-height: 1.7; color: #486581;">
            Tell us how we can help. We usually respond within 1-2 business days, and urgent booking issues are prioritized faster.
        </p>

        <div style="display:grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 18px;">
            <section style="border:1px solid #d9e6f2; border-radius:12px; padding:14px; background:#f8fbff;">
                <h2 style="margin:0 0 8px; font-family:'Fraunces',serif; font-size:1.1rem; color:#102a43;">Support Channels</h2>
                <p style="margin:0; color:#486581; line-height:1.6;">Email: <a href="mailto:{{ $supportEmail }}" style="color:#0d3b66; text-decoration:none; font-weight:700;">{{ $supportEmail }}</a></p>
                <p style="margin:6px 0 0; color:#486581; line-height:1.6;">Use the form below for account, booking, and policy requests.</p>
            </section>

            <section style="border:1px solid #d9e6f2; border-radius:12px; padding:14px; background:#f8fbff;">
                <h2 style="margin:0 0 8px; font-family:'Fraunces',serif; font-size:1.1rem; color:#102a43;">What To Include</h2>
                <ul style="margin:0; padding-left:18px; color:#486581; line-height:1.6;">
                    <li>Your account email</li>
                    <li>Booking ID (if applicable)</li>
                    <li>A short summary of the issue</li>
                </ul>
            </section>
        </div>

        @if(session('status'))
            <div role="status" style="background: #dcfce7; color: #14532d; border: 1px solid #86efac; border-radius: 12px; padding: 12px 14px; margin: 20px 0;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('contact.submit') }}" style="display: grid; gap: 14px; margin-top: 20px;" novalidate>
            @csrf

            {{-- Honeypot (Rulebook §23) — must stay empty --}}
            <input type="text" name="company" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="display:none;">

            {{-- Name (Rulebook §6: min 2, max 120) --}}
            <div>
                <label for="contact-name" style="font-weight: 700; color: #102a43; display: block; margin-bottom: 6px;">
                    Name <span style="color:#e03;">*</span>
                </label>
                <input
                    type="text"
                    id="contact-name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    minlength="2"
                    maxlength="120"
                    autocomplete="name"
                    aria-describedby="contact-name-error"
                    @error('name') aria-invalid="true" @else aria-invalid="false" @enderror
                    style="width: 100%; min-height: 46px; border: 1px solid {{ $errors->has('name') ? '#ef4444' : '#cbd5e1' }}; border-radius: 10px; padding: 0 12px; box-sizing:border-box;"
                >
                @error('name')
                    <p id="contact-name-error" role="alert" style="color:#dc2626; font-size:14px; margin-top:4px;">{{ $message }}</p>
                @else
                    <p id="contact-name-error" role="alert" style="min-height:18px;"></p>
                @enderror
            </div>

            {{-- Email (Rulebook §3: valid, max 254) --}}
            <div>
                <label for="contact-email" style="font-weight: 700; color: #102a43; display: block; margin-bottom: 6px;">
                    Email <span style="color:#e03;">*</span>
                </label>
                <input
                    type="email"
                    id="contact-email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="254"
                    autocomplete="email"
                    aria-describedby="contact-email-error"
                    @error('email') aria-invalid="true" @else aria-invalid="false" @enderror
                    style="width: 100%; min-height: 46px; border: 1px solid {{ $errors->has('email') ? '#ef4444' : '#cbd5e1' }}; border-radius: 10px; padding: 0 12px; box-sizing:border-box;"
                >
                @error('email')
                    <p id="contact-email-error" role="alert" style="color:#dc2626; font-size:14px; margin-top:4px;">{{ $message }}</p>
                @else
                    <p id="contact-email-error" role="alert" style="min-height:18px;"></p>
                @enderror
            </div>

            {{-- Subject (optional) --}}
            <div>
                <label for="contact-subject" style="font-weight: 700; color: #102a43; display: block; margin-bottom: 6px;">Subject (optional)</label>
                <input
                    type="text"
                    id="contact-subject"
                    name="subject"
                    value="{{ old('subject') }}"
                    maxlength="160"
                    style="width: 100%; min-height: 46px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; box-sizing:border-box;"
                >
            </div>

            {{-- Message (Rulebook §2: min 10, max 5000, char counter hint) --}}
            <div>
                <label for="contact-message" style="font-weight: 700; color: #102a43; display: block; margin-bottom: 6px;">
                    Message <span style="color:#e03;">*</span>
                </label>
                <textarea
                    id="contact-message"
                    name="message"
                    required
                    minlength="10"
                    maxlength="5000"
                    rows="7"
                    aria-describedby="contact-message-hint contact-message-error"
                    @error('message') aria-invalid="true" @else aria-invalid="false" @enderror
                    style="width: 100%; border: 1px solid {{ $errors->has('message') ? '#ef4444' : '#cbd5e1' }}; border-radius: 10px; padding: 12px; resize: vertical; box-sizing:border-box;"
                >{{ old('message') }}</textarea>
                <p id="contact-message-hint" style="font-size:13px; color:#6b7f92; margin-top:4px;">
                    Minimum 10 characters. Maximum 5000 characters.
                </p>
                @error('message')
                    <p id="contact-message-error" role="alert" style="color:#dc2626; font-size:14px; margin-top:2px;">{{ $message }}</p>
                @else
                    <p id="contact-message-error" style="min-height:18px;"></p>
                @enderror
            </div>

            <button type="submit" class="btn-pill primary" style="width: fit-content; border: none; cursor: pointer;">
                Send message
            </button>
        </form>

        <p style="margin: 16px 0 0; color: #6b7f92; line-height: 1.6; font-size: 14px;">
            By submitting this form, you agree that EduBridge may contact you regarding your request and retain relevant conversation logs
            for support quality and compliance purposes.
        </p>
    </article>
@endsection

