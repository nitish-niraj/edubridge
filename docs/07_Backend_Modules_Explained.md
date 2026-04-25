# Backend Modules Explained

## Application Layers

The backend is organized into familiar Laravel layers:

- Routes: endpoint mapping and middleware grouping
- Controllers: HTTP orchestration and response shaping
- Requests: input validation and authorization
- Services: external integrations/business logic orchestration
- Models: data access and relationship graph
- Jobs: async/queued operations
- Events: realtime and decoupled signaling
- Mail: email templates and payload contracts
- Middleware: request pipeline guards and policy checks

## Route Modules

### Public and Core Web Routes

- Defined in `routes/web.php`
- Include landing pages, static pages, contact, sitemap, authenticated dashboards

### API Routes

- Defined in `routes/api.php`
- Prefix-based route groups for modular APIs:
  - auth
  - student
  - teacher
  - booking
  - payment
  - chat
  - admin

### Auth Routes

- Defined in `routes/auth.php`
- Handles login/register/logout/password reset/verification endpoints

### Broadcast Channels

- Defined in `routes/channels.php`
- Authorizes private and presence channels for chat and user streams

## Controllers by Domain

### Public Experience

- `LandingController`
- `PageController`
- `SitemapController`

Responsibilities:

- render marketing/static pages
- submit contact form and dispatch mail
- provide sitemap XML

### Student Portal

Typical controllers:

- teacher discovery/search
- booking creation and management
- payment initiation and status tracking
- saved teachers and review actions

### Teacher Portal

Typical controllers:

- profile updates
- availability and slot management
- session/booking management
- earnings and payout-related endpoints

### Chat and Collaboration

- conversation/message endpoints
- participant and permission controls
- typing/events integrations with broadcasting

### Admin Portal

- user management
- report handling
- announcement publishing
- audit and analytics endpoints

## Request Validation

Located under `app/Http/Requests`.

Pattern:

- Validate payload structure and formats
- Centralize rules near domain use-cases
- Keep controllers focused on orchestration

## Middleware Stack

Important middleware in project context:

- auth and auth:sanctum/session based protections
- role checks for student/teacher/admin segmentation
- admin hardening middleware (for example 2FA requirement)
- security headers/CSP enforcement middleware
- request throttling and trusted proxy handling from Laravel kernel

## Service Layer

Located in `app/Services`.

Examples:

- payment gateway service wrappers (PhonePe)
- twilio/video session services
- potentially reusable orchestration logic used by multiple controllers/jobs

Design objective:

- isolate integration details from controllers
- improve testability and fault handling

## Jobs and Queue Processing

Jobs in `app/Jobs` include:

- notification jobs
- export/email jobs
- payment release/background processing

Queue config is environment-driven (`config/queue.php`).

Recommended local ops:

- run worker: `php artisan queue:work`
- monitor failed jobs in `failed_jobs`

## Events and Broadcasting

Event classes under `app/Events` include:

- chat and session related realtime signals (`MessageSent`, `UserTyping`, etc.)
- collaborative interaction events (`DrawPermissionGranted`, `WhiteboardUpdate`)

Broadcasting config lives in `config/broadcasting.php`, frontend consumes via Echo/Pusher in JS bootstrap.

## Mail Module

Mail classes under `app/Mail` model transactional and announcement emails.

Patterns:

- mailable receives a typed payload/model
- blade templates define user-facing content
- queueable mails can be dispatched asynchronously

## Error Handling and Resilience

- `app/Exceptions/Handler.php` controls exception rendering behavior.
- Controllers now include targeted resilience for DB outage cases on key public/API paths.
- Integration services avoid hard-crashing app bootstrap when optional SDKs are unavailable.

## Provider/Bootstrapping Layer

- `app/Providers` contains service provider registrations
- `bootstrap/app.php` handles framework boot wiring
- `app/Console/Kernel.php` can host scheduled jobs (currently minimal schedule usage)

## Security and Compliance Notes

- role-based authorization is central to every protected module
- audit and report flows support moderation/compliance operations
- upload security helper + validation rules mitigate file upload risks

## Testing Surface (Backend)

Primary backend tests are under:

- `tests/Feature`
- `tests/Unit`

Use tests to lock behavior when changing:

- route contracts
- role access control
- booking/payment transitions
- chat authorization logic
