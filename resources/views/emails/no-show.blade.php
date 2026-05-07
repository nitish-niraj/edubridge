@php
    $counterparty = $recipientRole === 'teacher'
        ? ($booking->student->name ?? 'your student')
        : ($booking->teacher->name ?? 'your teacher');
@endphp

<p>Hello {{ $recipientRole === 'teacher' ? ($booking->teacher->name ?? 'Teacher') : ($booking->student->name ?? 'Student') }},</p>

<p>
    The session scheduled at {{ optional($booking->start_at)->format('d M Y, h:i A') }}
    with {{ $counterparty }} has been marked as <strong>no-show</strong>.
</p>

<p>
    If this was incorrect, please contact support so the admin team can review the session activity.
</p>

<p>Thanks,<br>EduBridge Team</p>
