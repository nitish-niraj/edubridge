@extends('layouts.public')

@section('content')
    <article style="max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d9e6f2; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(16, 42, 67, .08);">
        <h1 style="font-family: 'Fraunces', serif; font-size: clamp(2rem, 4vw, 2.8rem); margin-top: 0; color: #102a43;">Privacy Policy</h1>
        <p style="line-height: 1.7; color: #486581;">Last updated: {{ now()->format('F j, Y') }}</p>
        <p style="line-height: 1.7; color: #486581;">
            This Privacy Policy explains what information EduBridge collects, why we collect it, and how we protect it.
            It applies to students, teachers, and visitors who use our website and platform services.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">1. Information We Collect</h2>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li><strong>Account data:</strong> name, email address, phone number, role, and login details.</li>
            <li><strong>Profile data:</strong> teacher bio, subjects, availability, experience, and verification details where applicable.</li>
            <li><strong>Booking and payment data:</strong> class schedules, booking history, transaction metadata, and payment status records.</li>
            <li><strong>Session and platform usage data:</strong> activity logs, support requests, device/browser metadata, and error diagnostics.</li>
            <li><strong>User submissions:</strong> reviews, ratings, messages, and contact form submissions.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">2. How We Use Information</h2>
        <p style="line-height: 1.7; color: #486581;">We use personal data to:</p>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Create and manage user accounts.</li>
            <li>Enable bookings, payments, reminders, and class operations.</li>
            <li>Provide user support, dispute resolution, and account security.</li>
            <li>Improve product quality, reliability, and user experience.</li>
            <li>Meet legal and compliance obligations where required.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">3. Cookies and Analytics</h2>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge uses essential cookies for authentication, session continuity, and security. Optional analytics tools are enabled only after
            consent where applicable, and are used to understand usage patterns and improve platform performance.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">4. Data Sharing</h2>
        <p style="line-height: 1.7; color: #486581;">We do not sell personal data. We may share data with:</p>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Service providers supporting hosting, communication, authentication, and payment processing.</li>
            <li>Teachers or students as necessary to deliver booked classes and support requests.</li>
            <li>Regulatory or legal authorities where disclosure is required by applicable law.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">5. Data Retention</h2>
        <p style="line-height: 1.7; color: #486581;">
            We retain personal data only as long as needed for account operations, support, legal compliance, fraud prevention,
            and dispute management. Retention timelines may vary by data category and legal requirement.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">6. Data Security</h2>
        <p style="line-height: 1.7; color: #486581;">
            EduBridge applies layered safeguards including encrypted transmission, access controls, and service monitoring.
            No system is perfectly secure, but we continuously improve controls to reduce risk.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">7. Your Rights and Choices</h2>
        <p style="line-height: 1.7; color: #486581;">Depending on your region, you may request to:</p>
        <ul style="line-height: 1.7; color: #486581; padding-left: 18px;">
            <li>Access a copy of personal information associated with your account.</li>
            <li>Correct inaccurate or incomplete personal details.</li>
            <li>Delete account data, subject to legal and operational retention requirements.</li>
            <li>Manage cookie preferences where available.</li>
        </ul>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">8. Policy Updates</h2>
        <p style="line-height: 1.7; color: #486581;">
            We may revise this policy to reflect legal, technical, or product updates. Material changes will be reflected by an updated
            "Last updated" date and, where appropriate, additional notice in the product.
        </p>

        <h2 style="font-family: 'Fraunces', serif; color: #102a43; margin-top: 28px;">9. Contact for Privacy Requests</h2>
        <p style="line-height: 1.7; color: #486581;">
            For privacy-related questions or requests, please use our <a href="{{ route('contact') }}" style="color:#0d3b66; text-decoration:none; font-weight:700;">Contact page</a>
            and include "Privacy Request" in the subject line.
        </p>
    </article>
@endsection
