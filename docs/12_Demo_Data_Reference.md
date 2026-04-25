# Demo Data Reference

## What This Adds

`DemoDataSeeder` adds linked reference data on top of the base users/profiles.

It seeds:

- saved teachers
- realistic booking slots and bookings in multiple states
- payment records in different lifecycle states
- teacher earning records
- booking events timeline entries
- reviews and profile rating recalculation
- direct and group chat conversations
- class member rows (including mute/draw variations)
- one pending moderation report
- one active admin announcement

## Seeder Pipeline Order

`DatabaseSeeder` now calls:

1. `RoleSeeder`
2. `AdminSeeder`
3. `TeacherSeeder`
4. `StudentSeeder`
5. `DemoDataSeeder`

## How To Seed

Run migrations and seed all data:

```bash
php artisan migrate
php artisan db:seed
```

Run only demo relational dataset (after base users/profiles already exist):

```bash
php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
```

## Default Login Accounts

From base seeders:

- Admin
  - email: `admin@edubridge.com`
  - password: `Admin@123`
- Teachers
  - password: `Teacher@123`
  - sample emails include `@teacher.com`
- Students
  - password: `Student@123`
  - sample emails include `@student.com`

## Demo Booking Scenarios

The seeded set includes representative records for testing screens/APIs:

- pending + unpaid booking
- confirmed + held payment booking
- completed + released payment booking
- cancelled + refunded booking
- completed free booking

These support testing:

- student booking lists and detail views
- teacher session management pages
- payment/release/refund paths
- review submission/read behavior

## Demo Chat Scenarios

- one direct student-teacher conversation with messages
- one group conversation with teacher + student participants
- announcement-type message in group
- member permission variation (`is_muted`, `can_draw`)

These support testing:

- conversation listing
- unread counts
- group moderation controls
- announcement pin/display flows

## Admin-Facing Demo Signals

- one pending report entry for moderation queue
- one active announcement

These help populate admin dashboard/action surfaces in non-empty states.

## Idempotence Behavior

`DemoDataSeeder` is designed to be rerunnable.

- It uses upsert/first-or-create patterns for most entities.
- Known demo records are keyed by deterministic markers (for example booking notes and conversation titles).
- Re-running updates existing demo rows instead of continuously duplicating core records.
