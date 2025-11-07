# Shovel Platform

## Overview
Shovel is a Laravel 11 application delivering the "Handshake" user experience: AI conversations escalate to human agents exactly when judgment matters. An embedded SQLite database keeps all customer data inside the app while enabling a clear upgrade path to Postgres.

### MVP outcomes
- Faster resolutions at lower cost with auditable handoffs
- Seamless transition between AI agent and human agent
- Skills-based routing, SLA tracking, and audit-ready logs

## Tech stack & key patterns
- **Framework:** Laravel 11, PHP 8.3
- **State orchestration:** `sebdesign/laravel-state-machine` for conversation lifecycle transitions
- **RBAC:** `spatie/laravel-permission` for roles and permissions
- **Queues:** Database driver (SQLite WAL) with future Horizon support
- **Front-end delivery:** Inertia.js + existing Laravel Blade components
- **LLM integration:** OpenAI-compatible API, model configurable via env (target `gpt-5`, fallback `gpt-4o/4.1`)
- **Data storage:** Embedded SQLite (`storage/database/database.sqlite`), WAL enabled; schema migration-compatible with Postgres
- **Testing:** Pest (PHPUnit compatible) with focus on state transitions, services, and API endpoints
- **Configuration pattern:** Environment-driven, sensitive values managed via `.env`

### Core permissions matrix

| Role | Permissions |
| --- | --- |
| Administrator | `dashboard.view`, `users.manage`, `queues.manage`, `conversations.review`, `guardrails.adjust`, `api-keys.manage`, `audit-logs.view` |
| Supervisor | `dashboard.view`, `queues.manage`, `conversations.review` |
| Human Agent | `dashboard.view`, `conversations.review` |

## Current functional coverage (Nov 2025)
| Requirement | Status | Notes |
| --- | --- | --- |
| FR-1 Authentication & RBAC | In progress | Spatie roles/permissions configured; admin guard wiring ongoing |
| FR-2 Conversation lifecycle | Partial | State machine defined; enqueue/assignment paths stubbed |
| FR-3 Agent runner & FR-14 Return-to-agent | Not started | Model contract + return-to-agent trigger pending |
| **FR-4 Handoff rules engine** | **Not started** | No configurable thresholds; handoff endpoint trusts caller payload |
| FR-5 Queues & routing | Partial | Queue tables created; assignment logic WIP |
| FR-6 Human inbox | Not started | UI scaffolding pending |
| FR-7 Conversation view | Not started | Transcript and context panel not yet implemented |
| FR-8 SLA management | Not started | Timers, breach alerts, and UI indicators unimplemented |
| FR-9 Audit & compliance | Not started | Audit export endpoints and UI missing |
| FR-10 Analytics | Not started | Reporting queries + charts not yet built |
| **FR-11 Admin settings** | In progress | Users/roles, skills, queues, and webhooks management UIs live; thresholds & policy packs pending |
| FR-12 API/webhooks | In progress | Admin webhook management UI complete; dispatch pipeline TBD |
| FR-15 Policy packs & redaction | Not started | Regex storage and masking pipeline absent |
| Observability (NFR) | Not started | Lacks health endpoint, metrics, log rotation |

## Immediate focus: FR-4 Handoff rules engine
1. **Data model:** Introduce tables for handoff policies (reason codes, confidence thresholds, required skills, metadata)
2. **Evaluation service:** Central service evaluates agent output against policies before enqueueing
3. **Admin UI:** Authenticated CRUD screens to manage thresholds and skills requirements

## Upcoming roadmap
1. SLA management (FR-8): timers, breach detection, queue escalation wiring
2. Audit exports (FR-9): JSON/CSV endpoints plus admin download actions
3. Analytics foundations (FR-10): reporting queries, baseline charts, API exposure
4. Admin settings (FR-11): surface thresholds, webhooks, policy packs in settings
5. Webhooks (FR-12): subscription model with signing and dispatch pipeline
6. Policy packs & redaction (FR-15): regex storage, masking pipeline
7. Observability (NFR): health endpoint, metrics, log rotation

## Architectural notes
- **State-driven transitions:** Conversation state machine governs all lifecycle events. Services should trigger transitions instead of mutating data directly.
- **Auditability:** Every transition should record context via the lifecycle service to maintain compliance history.
- **Skills metadata:** Queues store required skills (`queues.skills_required`). Upcoming handoff policies must reference compatible skills for routing.
- **Environment awareness:** Features must consider dev/test/prod using existing config patterns; avoid hardcoded environment logic.

## Testing guidance
- Focus on regression tests around state machine transitions and service layer logic.
- Add feature/API tests for admin CRUD endpoints and handoff evaluation outcomes once implemented.
- Keep mocked data limited to test fixtures; never seed for dev/prod environments.

## Operational checklist
- SQLite WAL mode enabled; plan nightly backups (per PRD).
- Ensure agent runner jobs log state transitions and payloads for audit trails.
- Before running new servers, kill existing related processes (per project rules).

## Change log scaffold
- **2025-11-06:** Added admin webhooks management UI details and updated feature coverage.
- **2025-11-04:** Initial README created from PRD to track stack, status, and roadmap.
