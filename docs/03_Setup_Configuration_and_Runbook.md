# Setup, Configuration, and Runbook

## 1) Local Prerequisites

- PHP 8.1+
- Composer
- Node.js 18+
- npm
- MySQL/MariaDB
- Optional: Redis (if switching queue/cache/session drivers)

## 2) Install and Boot

From project root:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Update `.env` with correct DB and service credentials.

Run DB migrations and seed data:

```bash
php artisan migrate
php artisan db:seed
```

Build frontend assets:

```bash
npm run build
```

Serve app:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## 3) Required Environment Variables

Key groups:

- App/runtime
  - `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- Database
  - `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Queue/cache/session
  - `QUEUE_CONNECTION`, `CACHE_DRIVER`, `SESSION_DRIVER`
- Mail
  - `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`
  - `CONTACT_FORM_TO_EMAIL`
- Broadcast
  - `BROADCAST_DRIVER`, `PUSHER_APP_*`, `VITE_PUSHER_APP_*`
- Search
  - `SCOUT_DRIVER`, `TNTSEARCH_*`, `MEILISEARCH_*`
- Twilio
  - `TWILIO_ACCOUNT_SID`, `TWILIO_API_KEY`, `TWILIO_API_SECRET`, `TWILIO_AUTH_TOKEN`
- Payment
  - `PHONEPE_CLIENT_ID`, `PHONEPE_CLIENT_VERSION`, `PHONEPE_CLIENT_SECRET`, `PHONEPE_ENV`

Reference template: `.env.example`

## 4) Queue and Background Work

Default queue uses database driver (`config/queue.php`).

Run worker:

```bash
php artisan queue:work
```

Inspect failed jobs:

```bash
php artisan queue:failed
php artisan queue:retry all
```

## 5) Frontend Build/Serve Modes

Development:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

Important: public Blade pages rely on `public/build/manifest.json`. If build is stale/missing, pages may fail.

## 6) Useful Operational Commands

Routes:

```bash
php artisan route:list
php artisan route:list --path=api
```

Config and cache:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Tests:

```bash
php artisan test
```

## 7) Seed Data

Primary seeders:

- `RoleSeeder`
- `AdminSeeder`
- `TeacherSeeder`
- `StudentSeeder`
- `DemoDataSeeder`

`DemoDataSeeder` creates realistic relational test data for bookings, payments, chats, reviews, reports, and announcements.

Run all:

```bash
php artisan db:seed
```

Run demo dataset only:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
```

## 8) Health and Smoke Checks

- API health endpoint: `GET /api/health`
- Public page checks: `/`, `/contact`, `/sitemap.xml`
- Auth page checks: `/login`, `/register/student`, `/register/teacher`
- Teacher listing checks: `/teachers`, `/api/teachers`

## 9) Production Readiness Checklist

- `APP_DEBUG=false`
- Proper SMTP (not log mailer)
- Valid queue worker supervisor config
- Correct APP_URL and trusted proxy settings
- CSP and security headers validated
- Stable DB storage and backup routine in place
