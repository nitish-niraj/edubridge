@extends('layouts.public')

@section('content')
    <article style="max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d9e6f2; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(16, 42, 67, .08);">
        <h1 style="font-family: 'Fraunces', serif; font-size: clamp(2rem, 4vw, 2.8rem); margin-top: 0; color: #102a43;">Terms and Conditions</h1>
        <p style="line-height: 1.7; color: #486581;">Last updated: {{ now()->format('F j, Y') }}</p>
        <p style="line-height: 1.7; color: #486581;">
            These Terms and Conditions govern your use of EduBridge. By using the website or platform,
            you agree to follow these terms and all applicable policies referenced in them.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">1. Eligibility and Account Registration</h2>
        <p style="line-height: 1.7; color: #486581;">
            Users must provide accurate registration details and maintain account security.
            You are responsible for activities performed through your account credentials.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">2. Platform Role</h2>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge provides digital tools for teacher discovery, session booking, payment workflows, and class coordination.
            We do not guarantee specific educational outcomes for every learner.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">3. Bookings, Payments, Cancellations</h2>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Bookings are confirmed only after successful checkout and availability checks.</li>
            <li>Prices, slot timings, and booking terms are shown before payment confirmation.</li>
            <li>Cancellation and refund eligibility depends on booking policy and timing.</li>
            <li>Disputed sessions may require supporting details from both learner and teacher.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">4. User Conduct</h2>
        <p style="line-height: 1.7; color: #486581;">Users must not misuse EduBridge services. Prohibited behavior includes:</p>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Harassment, abuse, discrimination, or threats in any communication channel.</li>
            <li>Fraud, impersonation, forged credentials, or payment abuse.</li>
            <li>Attempts to bypass platform safeguards or access unauthorized data.</li>
            <li>Sharing harmful, illegal, or misleading content on the platform.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">5. Teacher and Student Responsibilities</h2>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li><strong>Teachers:</strong> keep profile details current, conduct classes professionally, and honor booked commitments.</li>
            <li><strong>Students:</strong> join sessions on time, communicate respectfully, and use platform tools responsibly.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">6. Content and Intellectual Property</h2>
        <p style="line-height: 1.7; color: #486581;">
            Platform design, branding, and system content belong to EduBridge or licensed providers.
            Users retain rights to their own lawful submissions while granting us limited rights to operate and display such content within the service.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">7. Service Availability and Changes</h2>
        <p style="line-height: 1.7; color: #486581;">
            We aim for reliable uptime, but temporary interruptions may occur due to maintenance, upgrades, or third-party service issues.
            Features may evolve over time to improve stability, compliance, and user experience.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">8. Suspension and Termination</h2>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge may restrict or terminate access for violations of these terms, policy abuse, fraud indicators,
            or legal/compliance reasons. Users may also request account closure through support.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">9. Updates to Terms</h2>
        <p style="line-height: 1.7; color: #486581;">
            We may update these terms periodically. Continued use of EduBridge after updates means acceptance of the revised terms.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">10. Contact</h2>
        <p style="line-height: 1.7; color: #486581;">
            For questions regarding these Terms and Conditions, please use our
            <a href="{{ route('contact') }}" style="color:#0d3b66; text-decoration:none; font-weight:700;">Contact page</a>.
        </p>
    </article>
@endsection
