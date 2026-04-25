# Codebase Map

## Top-Level Structure

- `app/` core backend code
- `bootstrap/` app bootstrap
- `config/` framework and service config
- `database/` migrations, factories, seeders
- `public/` web root and build assets
- `resources/` frontend source, Blade views, CSS/JS
- `routes/` web/api/auth/channels routes
- `storage/` logs, framework cache, app files
- `tests/` feature and integration tests
- `docs/` project documentation

## app/ Breakdown

### Controllers

- `app/Http/Controllers/Auth/*` login/register/password/social/otp
- `app/Http/Controllers/Api/*` chat, teachers, bookings, payments, reviews, reports, group/video endpoints
- `app/Http/Controllers/Student/*` student dashboard/profile/onboarding
- `app/Http/Controllers/Teacher/*` teacher dashboard/profile/availability
- `app/Http/Controllers/Admin/*` admin verification/users/reports/analytics/announcements/disputes
- Public/static controllers:
  - `LandingController`
  - `PageController`
  - `SitemapController`

### Models

Main entities:

- Identity and profile: `User`, `StudentProfile`, `TeacherProfile`
- Discovery and engagement: `SavedTeacher`, `Review`, `Feedback`
- Booking and payments: `Booking`, `BookingSlot`, `Payment`, `TeacherEarning`, `BookingEvent`
- Messaging and groups: `Conversation`, `ConversationParticipant`, `Message`, `ClassMember`
- Admin moderation: `Report`, `Announcement`, `AuditLog`
- Video/session support: `VideoSession`

### Services

- `BookingService` booking lifecycle logic helpers
- `PhonePeService` payment gateway wrapper
- `TwilioService` video/token helpers
- `SeoService` SEO metadata composition

### Jobs

- `SendChatNotification`
- `SendBookingConfirmationNotification`
- `SendCancellationNotification`
- `ReleasePayment`
- `ExportUsersJob`
- `BulkAnnouncementEmailJob`

### Events

- `MessageSent`
- `UserTyping`
- `WhiteboardUpdate`
- `GroupSessionStarted`
- `RecordingConsentRequest`
- `DrawPermissionGranted`

### Middleware

- `RoleMiddleware`
- `EnsureAdminTwoFactorVerified`
- `SecurityHeaders`
- `ForceHttps`
- plus standard Laravel middleware

## resources/ Breakdown

### Vue SPA Source

- Entry: `resources/js/app.js`
- Axios + Echo: `resources/js/bootstrap.js`
- Pages:
  - `resources/js/Pages/Admin/*`
  - `resources/js/Pages/Auth/*`
  - `resources/js/Pages/Student/*`
  - `resources/js/Pages/Teacher/*`
  - shared pages like `VideoSession.vue`, `GroupVideoSession.vue`
- Layouts:
  - `AdminLayout.vue`
  - `StudentLayout.vue`
  - `TeacherLayout.vue`
  - `GuestLayout.vue`

### Blade Views

- App shell: `resources/views/app.blade.php`
- Public layout: `resources/views/layouts/public.blade.php`
- Public pages: `resources/views/pages/*`
- Landing page: `resources/views/landing.blade.php`
- Sitemap view: `resources/views/sitemap.blade.php`

## Route Files

- `routes/web.php` page routes, portal routes, webhooks, role-gated sections
- `routes/api.php` data API and admin APIs
- `routes/auth.php` Breeze auth endpoints
- `routes/channels.php` broadcast channel authorization

## Database Layer

- Migrations under `database/migrations`
- Seeders under `database/seeders`
- Factories under `database/factories`

## Test Suite

Feature coverage under `tests/Feature/*` includes:

- Auth and registration
- Search and booking
- Chat and groups
- Reviews and admin
- Health/performance/security
