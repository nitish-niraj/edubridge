# Aggregated EduBridge Documentation (for Graphify indexing)

This single-file aggregation is created to ensure important project documentation is discoverable by the Graphify corpus when the `graphify-out` folder is excluded.

---

## 00_Documentation_Index.md

This folder is the primary knowledge base for the EduBridge project.

If you are new to the codebase, read in this order:

1. `00_Documentation_Index.md` (this file)
2. `01_Project_Overview.md`
3. `02_Architecture_and_Flow.md`
4. `03_Setup_Configuration_and_Runbook.md`
5. `04_Codebase_Map.md`
6. `05_Routes_and_API_Reference.md`
7. `06_Database_Models_and_Data_Guide.md`
8. `07_Backend_Modules_Explained.md`
9. `08_Frontend_Pages_and_UI_SYSTEM.md`
10. `09_Operations_TESTING_and_Release.md`
11. `10_Troubleshooting_and_Known_Issues.md`
12. `11_Extensibility_and_Future_RoadMAP.md`
13. `12_Demo_Data_Reference.md`

---

## 01_Project_Overview.md

EduBridge is a multi-portal tutoring platform connecting students with teachers (including retired/experienced educators), with admin controls for moderation, analytics, and operations.

Core tech:
- Backend: Laravel 10 (PHP)
- Frontend: Vue 3 + Inertia + Vite
- Database: MySQL
- Queue: database driver by default
- Realtime: Pusher + Echo

Important product areas: public pages, auth/OTP, discovery, chat, booking, payments, group sessions, admin governance.

---

## 10_Troubleshooting_and_Known_Issues.md (excerpt)

Fast triage flow:

1. Identify failing surface: public page, API, auth, queue, or realtime.
2. Reproduce with exact URL/role/payload.
3. Check `storage/logs/laravel.log` for stack traces.
4. Verify dependency health: DB, queue worker, mail config, broadcaster keys.
5. Apply targeted fix and retest the same path.

Known runtime risk areas (excerpt):
- Database availability and migration state
- Missing frontend asset manifest entries (Vite manifest)
- Optional SDK missing at runtime (payments)
- Mail deliverability
- Realtime channel/auth issues
- Queue worker availability and failed jobs

Debugging commands reference:
- `php artisan route:list`
- `php artisan optimize:clear`
- `php artisan migrate:status`
- `php artisan test`

---

## Manual Setup (zero-cost) — key points (excerpt)

- Local dev: use `sqlite` or local `mysql` (XAMPP). Set `MAIL_MAILER=log` for local email deliveries.
- Production (zero-cost) hosting options: Oracle Cloud Always Free or Render.com free tier.
- Filesystem: store on server disk if using Always Free tier.
- Real emails: Brevo (300/day free) or SendGrid (100/day free).

---

## Form Validation Rulebook (excerpt)

Golden rule: validate both on frontend (UX) and backend (security).

- Trim spaces, check min/max length, disallow blank-only input.
- Email: use standard regex; lower-case before storing.
- Password: min 8 chars, mixed-case, number + special char, confirm field required.

---

If you want the entire docs to be indexed as first-class nodes in the Graphify graph, we can:

1. Host the `docs/` folder at a public HTTP(S) URL and run `graphify add <public-url> --dir docs` (recommended for full fidelity).
2. Or move/copy the `docs/` folder into a non-ignored path inside the repository root (not `graphify-out`), then run `graphify update .`.
3. Or I can continue building a more-complete aggregated file here (it will be indexed by Graphify after `graphify update .`).

Current action: this aggregate file will be indexed on the next `graphify update .` run.
