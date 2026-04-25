# Frontend Pages and UI System

## Frontend Architecture

The project uses Vue 3 + Inertia for authenticated app modules and Blade for public/static pages.

- Inertia entry: `resources/js/app.js`
- Shared bootstrap/network/realtime setup: `resources/js/bootstrap.js`
- Public shell layout: `resources/views/layouts/public.blade.php`

## Rendering Modes

### Blade Public Pages

Used for:

- landing/home
- about/contact/legal style pages
- sitemap XML endpoint view

Assets are loaded through Vite with required manifest entries for both CSS and JS.

### Inertia + Vue Portal Pages

Used for:

- student dashboards and tools
- teacher workbench pages
- admin management panels

Benefits:

- server-driven routing with SPA-like transitions
- unified auth/session behavior through Laravel middleware

## Frontend Folder Guide

- `resources/js/Pages`: route-level Vue page components
- `resources/js/Components`: reusable leaf and section components
- `resources/js/Layouts`: shared page wrappers per role/context
- `resources/js/Composables`: reusable reactive logic hooks
- `resources/css`: Tailwind and custom style layers

## Global Bootstrapping

`resources/js/bootstrap.js` provides:

- axios defaults and CSRF headers
- dynamic API base URL derived from current host metadata
- Echo/Pusher initialization if runtime keys exist

This file is the first stop when debugging:

- API URL mismatch
- realtime not connecting
- CSRF/request credential issues

## Page Families

### Public Marketing/Discovery

- home and informational pages
- teacher listing/profile public views
- contact and legal pages

### Student Portal

- teacher search/discovery
- booking flow and booking history
- saved teachers
- payment checkout/status
- chat and session views

### Teacher Portal

- profile and availability management
- bookings and class/session execution
- earnings and report access
- communication modules

### Admin Portal

- user moderation/management
- reports and announcements
- audit and platform metrics
- support workflows

## UI Patterns

- role-specific layouts keep navigation/context stable per portal
- API-driven list/detail pages with loading and empty states
- toast/alert feedback for create/update actions
- pagination and filters for high-cardinality resources

## Error and Fallback UX

Recent hardening ensures:

- teacher search handles backend outages with user-friendly messages
- teacher profile page shows load error state instead of blank UI
- public pages avoid 500 failures caused by missing asset manifest entries

## Styling System

- Tailwind configured via `tailwind.config.js`
- PostCSS integration via `postcss.config.js`
- bundled by Vite (`vite.config.js`)

When changing global look-and-feel:

1. update utility/theme config
2. verify generated CSS is present in Vite manifest
3. test Blade + Inertia pages together

## Frontend Testing Guidance

Existing automated coverage is primarily backend-weighted. For frontend-sensitive changes:

- run feature tests that exercise related routes
- manually verify:
  - role-based nav
  - error states
  - API integration edge cases
  - mobile viewport behavior

## Future Frontend Improvement Opportunities

- increase component-level consistency docs for all role portals
- add formal E2E scenarios for key student/teacher/admin journeys
- centralize API error mapping utilities for consistent messaging
