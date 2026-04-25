# Database, Models, and Data Guide

## Core Database Design

EduBridge is centered on `users`, then extends role-specific and domain-specific tables.

## Identity and Profiles

- `users`
  - Common identity + auth + role + status
  - Roles: `student`, `teacher`, `admin`
- `student_profiles` (1:1 with users)
- `teacher_profiles` (1:1 with users)

Model relations:

- `User::studentProfile()`
- `User::teacherProfile()`

## Discovery and Reputation

- `saved_teachers` maps student favorites
- `reviews` stores post-session ratings/comments for teachers

Teacher-facing aggregate fields live in `teacher_profiles`:

- `rating_avg`
- `total_reviews`

## Messaging and Group Collaboration

- `conversations`
- `conversation_participants`
- `messages`
- `class_members` (group class role/mute/draw permissions)

This supports:

- 1:1 chat
- group chat
- teacher announcements
- membership moderation

## Booking and Session Lifecycle

- `teacher_availability` (availability definitions)
- `booking_slots` (bookable materialized slots)
- `bookings` (student-teacher booking records)
- `booking_events` (auditable lifecycle events)
- `video_sessions` (video room state + recording metadata)

## Payments and Earnings

- `payments` links 1:1 with bookings
- `teacher_earnings` tracks payout-related accounting rows

## Moderation and Administration

- `reports` for abuse/dispute handling
- `announcements` for admin broadcast
- `audit_logs` for operational traceability
- `feedbacks` for user feedback intake

## Additional Support Tables

- `verifications` for OTP flow
- `user_notification_preferences`
- queue tables (`jobs`, `failed_jobs`, `job_batches`)
- auth support (`password_reset_tokens`, `personal_access_tokens`)

## Important Eloquent Models (Non-Exhaustive)

- `User`
- `StudentProfile`
- `TeacherProfile`
- `SavedTeacher`
- `Conversation`
- `ConversationParticipant`
- `Message`
- `ClassMember`
- `Booking`
- `BookingSlot`
- `BookingEvent`
- `Payment`
- `TeacherEarning`
- `Review`
- `Report`
- `VideoSession`
- `Announcement`

## Data Integrity Notes

- Most foreign keys use cascading delete where appropriate.
- Several tables include unique constraints (for example one review per booking).
- Soft deletes are used for selected entities (for example `users`, `bookings`, `messages`).

## Seed Data

Default seed pipeline (`DatabaseSeeder`):

1. `RoleSeeder`
2. `AdminSeeder`
3. `TeacherSeeder`
4. `StudentSeeder`
5. `DemoDataSeeder`

Base seeders create login-ready users and profiles.

`DemoDataSeeder` adds relational sample data across:

- saved teachers
- booking slots and bookings (multiple statuses)
- payments and teacher earnings
- reviews with profile aggregate refresh
- conversations/messages/class members
- moderation reports and announcements

## Query Hotspots

- Teacher search and filtering from `TeacherProfile` + joined `users`
- Conversation/message timeline with pagination
- Booking list and status transitions
- Admin analytics aggregate queries

## Migration Organization

Migrations are grouped by feature evolution, including:

- initial user/profile schema
- chat/group schema
- booking/payment schema
- moderation/admin schema
- later additive enhancements (indexes, columns, feature flags)

Always inspect migration order before manual schema changes.
