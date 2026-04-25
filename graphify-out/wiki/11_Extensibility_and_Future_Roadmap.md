# Extensibility and Future Roadmap

## Purpose

This document explains how to safely extend EduBridge and proposes a practical roadmap for future improvements.

## Extension Principles

1. Preserve role boundaries (student, teacher, admin).
2. Prefer additive schema evolution via migrations.
3. Keep controllers thin and move reusable logic to services.
4. Add tests for every behavior change in critical flows.
5. Make integration failures graceful, never bootstrap-fatal.

## Where to Add New Features

### New HTTP Capabilities

- Add route in `routes/web.php` or `routes/api.php`
- Add/extend controller method
- Add request validator in `app/Http/Requests` if payloaded
- Add service class if logic crosses integration boundaries
- Add policy/middleware updates for authorization

### New Data Domain

- Create migration(s)
- Create/extend model + relations
- Update factories/seeders
- Add feature tests for CRUD and permission boundaries

### New Async Behavior

- Add job in `app/Jobs`
- Dispatch from controller/service
- Ensure queue worker and failure monitoring are documented

### New Realtime Event

- Add event class in `app/Events`
- Register channel authorization rule in `routes/channels.php`
- Bind frontend Echo listener in related Vue module

## Common Feature Expansion Ideas

## Learning Experience

- richer class tools (annotations, recording indexes, attachments)
- smarter matching/recommendation for teacher discovery
- student learning journey tracking and goal milestones

## Teacher Enablement

- dynamic pricing and package plans
- advanced availability templates and recurring slots
- teacher analytics dashboard enhancements

## Platform Governance

- deeper moderation workflows for reports/disputes
- stronger audit filtering and export tooling
- admin automation for repeated policy tasks

## Reliability and Scale Roadmap

1. Strengthen DB reliability and backup/restore routines.
2. Expand queue observability and retry policy automation.
3. Add centralized error catalog for API and UI mapping.
4. Increase integration contract tests (payments, mail, realtime).
5. Add lightweight performance baselines for top endpoints.

## Security Roadmap

- expand security header and CSP review cadence
- strengthen upload validation and scanning paths
- add periodic role-permission audit scripts
- review secret rotation and environment hygiene

## Testing Roadmap

1. Increase unit tests around services/helpers.
2. Expand feature tests for booking-payment edge cases.
3. Add E2E happy-path + failure-path checks for each role portal.
4. Add regression tests for prior outages (asset manifest, DB outage fallbacks).

## Developer Experience Roadmap

- standardize module-level README notes for complex areas
- improve fixture/seed data profiles for local QA scenarios
- add command aliases/scripts for common local operations

## Suggested Incremental Milestones

### Milestone A: Stability First

- DB hardening
- queue monitoring baseline
- API/UI error consistency pass

### Milestone B: Feature Depth

- discovery and booking UX enhancements
- teacher analytics and payout clarity
- moderation tooling upgrades

### Milestone C: Scale and Trust

- broader automated test matrix
- resilience drills for dependency outages
- release playbook maturity

## Definition of Done for New Features

A feature is complete when:

1. routes, controllers, models, and migrations are implemented
2. role authorization is validated
3. tests are added and passing
4. frontend states cover loading/empty/error/success
5. docs are updated in `docs/` for future maintainers
