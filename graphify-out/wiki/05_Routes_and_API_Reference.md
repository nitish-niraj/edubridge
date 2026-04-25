# Routes and API Reference

This document summarizes the functional route surface of EduBridge.

## Web Routes (`routes/web.php`)

### Public

- `GET /` -> landing page
- `GET /about-us`
- `GET /privacy-policy`
- `GET /terms-and-conditions`
- `GET /contact`
- `POST /contact`
- `GET /sitemap.xml`

### Public Discovery and Auth Entry

- `GET /teachers` (Inertia Student/TeacherSearch)
- `GET /teachers/{teacher}` (Inertia Student/TeacherPublicProfile)
- `GET /register/student`
- `POST /register/student`
- `GET /register/teacher`
- `POST /register/teacher`
- `GET /verify-otp`
- `POST /verify-otp`
- `POST /resend-otp`
- Google auth routes

### Student Web Portal (role-protected)

Prefix: `/student`

- `GET /student/dashboard`
- `GET /student/onboarding`
- `POST /student/onboarding`
- `GET /student/profile`
- `PATCH /student/profile`
- `GET /student/saved-teachers`
- `GET /student/chat`
- `GET /student/bookings`

### Teacher Web Portal (role-protected)

Prefix: `/teacher`

- `GET /teacher/dashboard`
- Profile wizard routes `/teacher/profile/step/{step}` + step POSTs
- `GET /teacher/chat`
- `GET /teacher/settings`
- `GET /teacher/availability`
- `POST /teacher/availability`
- `GET /teacher/sessions`
- `GET /teacher/classes/create`
- `GET /teacher/classes/{id}`

### Admin Web Portal

- Admin 2FA challenge routes
- Main admin pages under `/admin/*` with `admin.2fa` middleware

### Session and Utility Web Routes

- `GET /chat/{conversation?}` (role-aware chat page)
- `GET /payment/callback`
- `POST /api/webhooks/phonepe` (no CSRF)
- `POST /api/webhooks/twilio/recording-complete` (no CSRF)
- `GET /reviews/{bookingId}`
- `GET /session/{bookingId}`
- `GET /group-session/{conversationId}`
- `GET /join/{inviteCode}`

## API Routes (`routes/api.php`)

## Public API

- `GET /api/health`
- `GET /api/teachers`
- `GET /api/teachers/search`
- `GET /api/teachers/{teacher}`
- `GET /api/teachers/{id}/availability`
- `GET /api/groups/preview/{inviteCode}`
- `POST /api/feedback` (rate-limited)

## Authenticated API (`auth:sanctum`)

### Student Discovery and Saved Teachers

- `GET /api/students/saved-teachers`
- `POST /api/students/saved-teachers/{teacher_id}`
- `DELETE /api/students/saved-teachers/{teacher_id}`

### Chat

- `GET /api/conversations`
- `POST /api/conversations`
- `GET /api/conversations/{conversation}/messages`
- `POST /api/conversations/{conversation}/messages`
- `PATCH /api/conversations/{conversation}/read`

### Group/Class APIs

- `GET /api/groups`
- `POST /api/groups`
- `GET /api/groups/{id}`
- `POST /api/groups/join/{inviteCode}`
- Member add/remove/mute/draw permission endpoints

### Availability, Booking, Payment, Session

- `POST /api/teacher/availability`
- `GET /api/bookings`
- `POST /api/bookings`
- `GET /api/bookings/{id}`
- `PATCH /api/bookings/{id}/cancel`
- `POST /api/payments/initiate`
- Video session endpoints (1:1 + group start/join/end)
- Whiteboard sync and recording consent endpoints
- `GET /api/recordings/{sessionId}`

### Review and Reporting

- `POST /api/reviews`
- `POST /api/reports`

### Teacher Settings (role:teacher subgroup)

- `PATCH /api/teacher/preferences`
- `PATCH /api/teacher/profile`

## Admin API (`auth:sanctum + role:admin + admin.2fa`)

Prefix: `/api/admin`

- Dashboard summary
- User management and exports
- Reports moderation actions
- Reviews visibility controls
- Analytics endpoints
- Announcements CRUD
- Dispute handling actions

## Auth Routes (`routes/auth.php`)

- Login/logout
- Password reset flow
- Email verification flow
- Confirm password flow

## Broadcast Channels (`routes/channels.php`)

- `App.Models.User.{id}`
- `conversation.{conversationId}` with participant authorization

Presence behavior returns user payload when channel name starts with `presence-`.

## Practical Notes

- Route names are heavily used by Ziggy in frontend navigation.
- Inertia page routes and API routes are intentionally separated.
- Webhooks are web routes, not API routes, because they are part of payment/session callback flow.
