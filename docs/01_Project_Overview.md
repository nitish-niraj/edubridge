# EduBridge Project Overview

## What EduBridge Is

EduBridge is a multi-portal tutoring platform connecting students with teachers (including retired/experienced educators), with admin controls for moderation, analytics, and operations.

The platform includes:

- Public marketing pages and SEO endpoints (`/`, `/about-us`, `/contact`, `/sitemap.xml`)
- Student portal (discovery, chat, booking, reviews)
- Teacher portal (profile setup, availability, sessions, group classes)
- Admin portal (users, verifications, reports, analytics, announcements, disputes)
- API layer for SPA behavior
- Real-time events (chat, typing, whiteboard, group session signals)

## Core Tech Stack

- Backend: Laravel 10, PHP 8.1+
- Frontend: Vue 3 + Inertia.js + Vite
- Database: MySQL/MariaDB
- Queue: database queue driver by default (`QUEUE_CONNECTION=database`)
- Realtime: Pusher + Laravel Echo
- Payments: PhonePe integration (service-level abstraction)
- Video: Twilio video integration
- Search: Laravel Scout with TNTSearch
- Permissions: Spatie Laravel Permission

## High-Level Product Areas

- Public + SEO
- Authentication and OTP verification
- Student teacher discovery and saved teacher lists
- 1:1 and group chat/conversation system
- Booking and session lifecycle
- Payment initiation/callback/webhook handling
- Group classes and live group sessions
- Admin governance and business tooling

## Important Characteristics of This Codebase

- Multi-role routing with middleware-based access checks
- Inertia-rendered pages backed by Vue components
- API-first data interactions from frontend pages (axios)
- Feature tests for key workflows under `tests/Feature`
- Heavily modular controllers by domain (`Api`, `Student`, `Teacher`, `Admin`, `Auth`)

## What Is Production-Critical

- Route guards (`auth`, `role:*`, `admin.2fa`)
- Payment callback/webhook correctness
- Booking state transitions and idempotency
- Realtime channel authorization (`routes/channels.php`)
- Error handling and graceful fallbacks for unavailable services

## Current Runtime Considerations

- Missing/invalid MySQL data files can break data-heavy features (search, bookings).
- If PhonePe SDK classes are unavailable, payment paths fail gracefully at usage time (not at app boot).
- Public pages depend on Vite build artifacts in `public/build`.

See `10_Troubleshooting_and_Known_Issues.md` for details and recovery steps.
