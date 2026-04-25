# Troubleshooting and Known Issues

## Fast Triage Flow

1. Identify failing surface: public page, API, auth, queue, or realtime.
2. Reproduce with exact URL/role/payload.
3. Check `storage/logs/laravel.log` for stack traces.
4. Verify dependency health: DB, queue worker, mail config, broadcaster keys.
5. Apply targeted fix and retest the same path.

## Known Runtime Risk Areas

## 1) Database Availability/Integrity Issues

Symptoms:

- teacher listing/profile APIs fail
- aggregate stats fail
- random 500s around DB-backed pages

What to check:

- DB service is running
- credentials in `.env` are correct
- migration state is current
- table corruption/engine errors (if local MySQL instability exists)

Mitigations already in code:

- key controllers use graceful fallback behavior for DB exceptions
- API endpoints return explicit service-unavailable responses in outage scenarios

## 2) Missing Frontend Asset Manifest Entries

Symptoms:

- Blade public pages throw 500 with Vite manifest lookup errors
- CSS or JS appears missing after deploy

What to check:

- `vite.config.js` includes required input entries
- production assets were rebuilt (`npm run build`)
- `public/build/manifest.json` contains expected keys

Mitigations already in code:

- Vite input includes both `resources/css/app.css` and `resources/js/app.js`

## 3) Optional SDK Missing at Runtime (Payment Integration)

Symptoms:

- app crashes during bootstrap/route list due to missing class

What to check:

- payment SDK dependency installation
- service initialization path

Mitigations already in code:

- payment service defers hard failure until payment operations are called
- app bootstrap no longer crashes if optional SDK class is absent

## 4) Contact Mail Not Delivering to Inbox

Symptoms:

- contact form reports success but email not received

What to check:

- `MAIL_MAILER` and SMTP credentials
- spam/junk filters on destination inbox
- `CONTACT_FORM_TO_EMAIL` value
- logs for mail transport errors

Project default expectation:

- contact destination should be `nitishniraj06@gmail.com`

## 5) Realtime Chat/Presence Not Updating

Symptoms:

- typing indicator missing
- messages require refresh

What to check:

- pusher keys and cluster values
- backend broadcast driver config
- frontend Echo initialization
- channel authorization logic in `routes/channels.php`

## 6) Queue-Dependent Features Delayed/Not Executing

Symptoms:

- emails/notifications/exports not processed

What to check:

- queue worker is running
- queue driver matches environment capability
- failed job table entries

Fix path:

- start workers and retry failed jobs

## Auth and Role Access Issues

Symptoms:

- unexpected 403/redirect loops

What to check:

- user role in DB
- middleware chain on routes
- session/cookie domain config

## Debugging Commands Reference

- route map: `php artisan route:list`
- app logs: inspect `storage/logs/laravel.log`
- clear caches: `php artisan optimize:clear`
- migrate status: `php artisan migrate:status`
- test run: `php artisan test`

## Preventive Practices

- keep dependency installation in sync (`composer install`, `npm install`)
- run tests before merge/deploy
- rebuild assets after frontend config changes
- verify env diffs during release
- keep docs updated for new env keys and integration behavior
