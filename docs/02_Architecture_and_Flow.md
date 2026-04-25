# Architecture and Request Flow

## System Architecture

EduBridge uses a Laravel monolith with an Inertia.js SPA frontend.

Main layers:

1. Browser client (Vue pages, shared layouts, components)
2. Inertia web routes for page shells and role-based navigation
3. API routes for async data operations
4. Domain controllers/services/models
5. Database + queue + event broadcasting

## Request Flow Types

### 1) Public Page Flow

- Browser requests web route (for example `/about-us`)
- Controller returns Blade view (`resources/views/pages/...`)
- Shared public layout renders SEO tags and global nav/footer

### 2) Inertia SPA Flow

- Browser requests web route (for example `/teachers`, `/student/dashboard`)
- Laravel returns Inertia response with page component name and props
- Frontend bootstraps in `resources/js/app.js`
- Vue component loads and then calls API endpoints via axios as needed

### 3) API Data Flow

- Frontend calls `/api/...` endpoint
- Request validation via FormRequest classes
- Controller executes business logic (query/service)
- Resource classes shape JSON response
- Frontend updates reactive state

### 4) Realtime Event Flow

- API/controller persists message or whiteboard action
- Event class broadcasts over channel (`conversation.{id}`)
- Subscribed clients receive event through Echo/Pusher
- UI updates without full refresh

### 5) Queue Flow

- Controller dispatches job (email, exports, notifications, payment release)
- Worker (`php artisan queue:work`) processes queued jobs
- System writes job state to `jobs` / `failed_jobs`

## Application Entry and Bootstrapping

- HTTP kernel binding: `bootstrap/app.php`
- Web middleware stack and global middleware: `app/Http/Kernel.php`
- Inertia app shell: `resources/views/app.blade.php`
- JS app entry: `resources/js/app.js`
- Axios + Echo init: `resources/js/bootstrap.js`

## Security and Access Control Layers

- Authentication guard checks: built-in Laravel auth + Sanctum
- Role checks: `app/Http/Middleware/RoleMiddleware.php`
- Admin 2FA gate: `app/Http/Middleware/EnsureAdminTwoFactorVerified.php`
- Broadcast channel auth: `routes/channels.php`
- HTTP headers and CSP: `app/Http/Middleware/SecurityHeaders.php`

## Data Ownership Model (Core)

- `users` is the root identity table
- `student_profiles` and `teacher_profiles` are 1:1 role detail tables
- Bookings connect students and teachers via `bookings` + `booking_slots`
- Messaging is conversation-based (`conversations`, `conversation_participants`, `messages`)
- Admin workflows use reports, announcements, verification records

## Deployment Model (Typical)

- Serve app via Apache/Nginx to `public/`
- Build frontend assets with `npm run build`
- Run migrations and seeders in CI/CD or release scripts
- Run queue worker continuously for async tasks
- Configure Pusher/Twilio/payment credentials via environment variables
