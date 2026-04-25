# Operations, Testing, and Release

## Daily Development Workflow

1. Pull latest code and install dependencies.
2. Ensure `.env` is configured from `.env.example`.
3. Start Laravel and Vite dev servers.
4. Run migrations and seeders where needed.
5. Execute tests before pushing changes.

## Local Runbook

### Backend

- install PHP deps: `composer install`
- app key: `php artisan key:generate`
- migrate: `php artisan migrate`
- optional seed: `php artisan db:seed`
- run server: `php artisan serve`

### Frontend

- install Node deps: `npm install`
- dev mode: `npm run dev`
- prod build: `npm run build`

## Environment Configuration Checklist

Critical env keys:

- app: `APP_ENV`, `APP_URL`, `APP_KEY`
- db: `DB_*`
- mail: `MAIL_*`, `CONTACT_FORM_TO_EMAIL`
- queue/cache/session drivers
- pusher/realtime keys
- twilio/video keys
- phonepe/payment credentials

Rule: keep production secrets out of source control.

## Queue and Background Jobs

Queue behavior is configured in `config/queue.php`.

Recommended operations:

- worker: `php artisan queue:work`
- inspect failures: `php artisan queue:failed`
- retry failures: `php artisan queue:retry all`

Use queue workers in environments where mail, notifications, exports, and delayed tasks must be asynchronous.

## Scheduling

- Scheduler config lives in `app/Console/Kernel.php`.
- Current project has minimal scheduled tasks.

If adding schedule tasks:

1. implement command/job
2. register schedule entry
3. configure system cron to run `schedule:run`

## Realtime Operations

Realtime stack requires:

- backend broadcaster config (`config/broadcasting.php`)
- frontend Echo/Pusher initialization (`resources/js/bootstrap.js`)
- valid channel authorization rules (`routes/channels.php`)

Smoke test after changes:

- open two user sessions
- send chat message/typing event
- confirm live update appears without refresh

## Testing Strategy

Test directories:

- `tests/Feature`: HTTP, auth, role, flow-level behavior
- `tests/Unit`: model/helper/service focused tests
- `tests/Browser`: Dusk browser tests where applicable

Core commands:

- all tests: `php artisan test`
- phpunit direct: `vendor/bin/phpunit`
- browser tests: `php artisan dusk` (if environment is configured)

## Release Preparation Checklist

1. Ensure migrations are forward-safe.
2. Ensure new env vars are documented.
3. Run backend tests.
4. Build frontend assets for production.
5. Verify role-specific critical paths:
   - student booking and payment
   - teacher availability/session management
   - admin moderation/report handling
6. Validate contact form mail delivery path.
7. Validate sitemap endpoint and robots indexing expectations.

## Deployment Notes

For traditional PHP hosting:

- deploy code
- install composer deps with optimized autoload
- run migrations
- clear/rebuild caches
- publish/build frontend assets
- restart queue workers/process managers

Useful artisan commands:

- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan optimize:clear`

## Monitoring and Logs

Primary log path:

- `storage/logs/laravel.log`

Operational monitoring focus:

- DB connection stability
- queue backlog/failure counts
- mail send failures
- payment callback anomalies
- broadcast/auth channel errors

## Incident Response Baseline

When a production issue occurs:

1. capture endpoint, user role, and timestamp
2. inspect application log entries for matching trace
3. validate dependency health (DB, queue, broadcaster, mail)
4. rollback or hotfix using smallest safe change
5. add regression test where feasible
