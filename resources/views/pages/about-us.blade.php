@extends('layouts.public')

@section('content')
    <article style="max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d9e6f2; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(16, 42, 67, .08);">
        <h1 style="font-family: 'Fraunces', serif; font-size: clamp(2rem, 4vw, 2.8rem); margin-top: 0; color: #102a43;">About EduBridge</h1>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge is an online learning platform built to make quality education more accessible and more consistent.
            We connect students with verified teachers and provide secure workflows for discovery, booking, live sessions,
            and post-class feedback in one place.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">Why EduBridge Exists</h2>
        <p style="line-height: 1.7; color: #486581;">
            Families often struggle with three problems: finding trusted teachers, coordinating practical schedules,
            and maintaining consistency over time. EduBridge was created to solve this gap with a reliable marketplace
            and a clear end-to-end learning journey.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">Our Mission</h2>
        <p style="line-height: 1.7; color: #486581;">
            To help learners grow through trusted mentorship and structured support, regardless of location,
            while giving teachers the tools to build sustainable and impactful teaching practices.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">What We Value</h2>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li><strong>Trust first:</strong> teacher credibility and profile quality are central to our platform decisions.</li>
            <li><strong>Student safety:</strong> privacy, secure access controls, and clear reporting paths are built into the experience.</li>
            <li><strong>Transparency:</strong> pricing, schedules, and class expectations are visible before booking.</li>
            <li><strong>Progress orientation:</strong> every class should help learners move toward measurable goals.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">How EduBridge Supports Learning</h2>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Teacher discovery with subject fit, ratings, and profile clarity.</li>
            <li>Simple booking and payment steps that reduce scheduling friction.</li>
            <li>Live session workflows designed for focus and continuity.</li>
            <li>Review and feedback loops that improve each next class.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">Who We Serve</h2>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge supports school learners, college students, working professionals, and teachers who want a modern,
            transparent, and dependable teaching-learning ecosystem.
        </p>

        <div style="margin-top: 32px; padding: 18px; border: 1px solid #d9e6f2; border-radius: 14px; background: #f8fbff;">
            <p style="margin: 0 0 10px; color: #334e68; line-height: 1.65;">
                Have partnership, onboarding, or support questions?
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="{{ route('contact') }}" class="btn-pill primary" style="text-decoration: none;">Contact our team</a>
                <a href="{{ route('teachers.index') }}" class="btn-pill secondary" style="text-decoration: none;">Explore teachers</a>
            </div>
        </div>
    </article>
@endsection
