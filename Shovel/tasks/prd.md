Purpose and background

 Problem: Customers get stuck in bot loops. Agents burn out on repetitive work. Leaders can’t prove compliance when AI is involved.
Vision: The Handshake—AI speed with human judgment. AI handles routine; humans take over at the exact moment judgment matters.
Primary outcome: Faster resolutions, lower cost, higher trust, full auditability.
Goals and non-goals
 Goals (MVP)
Seamless agent-to-human handoff based on rules and confidence.
End-to-end conversation thread with full context preserved.
Skills-based routing, SLAs, and audit trails.
“Return to Agent” to loop AI back in with human notes.
All data stored “inside the app” (embedded DB).
 Non-goals (MVP)
Omnichannel voice, payments, or custom agent tools marketplace.
Full multi-tenant billing and usage-based invoicing.
Deep workforce management (shifts, forecasting).
 Target users and personas
 Requester (end user or upstream system): Starts a conversation, expects quick, trustworthy outcomes.
Agent Runner (LLM agent): Replies, flags risk, requests handoff.
Human Agent: Claims cases, replies with empathy, returns to agent or resolves.
Supervisor/QA: Monitors SLAs, audits outcomes, sets rules.
Admin: Manages roles, queues, rules, webhooks, API keys.
Optional: Compliance Officer: Reviews audit logs, policy packs, and QA samples.
 Top use cases and user journeys
 Customer support triage: AI answers simple questions; hands off on low confidence or policy flags; human resolves; optionally returns to AI.
Document or content review: AI summarizes; human approves or edits; system logs all changes and decisions.
Sales qualification: AI captures lead details; hands off for price or objections; human closes; AI sends follow-up.
 Scope
 In scope (MVP)
Web app with login and RBAC (Admin, Human Agent, Supervisor).
REST API to start conversations and post messages.
Single agent provider using OpenAI API (target model GPT-5 when available; fallback GPT-4o/4.1).
Rules-based handoff (confidence, policy flags, tool error, SLA risk).
Queues, assignments, skills routing, round-robin fallback.
SLA timers and breach alerts.
Human console: inbox, conversation view, reply, resolve, return to agent.
Audit logging and downloadable transcript.
Basic analytics (handoff rate, time-to-first-response, time-to-resolution).
Embedded database (SQLite with WAL).
 Later (post-MVP)
Email/Slack channels, attachments, KB authoring UI, QA scoring rubrics, sampling plans, multi-tenant SaaS, SSO, webhooks management UI, redaction UI, dashboards 2.0, real-time websockets.
 Success metrics (12 weeks post-launch)
 Handoff accuracy: ≥90% of handoffs occur for valid reasons (low confidence/policy/tool/SLA).
Time to first response: ≤15 seconds (agent) median.
Time to resolution: 30% faster vs. baseline (manual only).
SLA compliance: ≥95% for standard priority, ≥98% for high priority.
QA sample pass rate: ≥90%.
Cost per resolved conversation: ≤70% of manual baseline.
Agent satisfaction (internal survey): +25% improvement.
Zero critical PII incidents.
 Functional requirements (FR)
FR-1 Authentication and RBAC
 Email/password login. Roles: Admin, Human Agent, Supervisor. Permissions via RBAC.
FR-2 Conversation lifecycle
Create conversation, post messages, state transitions: new → agent_working → needs_human → queued → assigned → human_working → back_to_agent → agent_working → resolved → archived.
FR-3 Agent runner
Calls OpenAI model. System prompt enforces structured JSON output: response, confidence (0–1), handoff (bool), reason (enum), policy_flags (array).
Thresholds configurable. If malformed output, auto-handoff with uncertain_intent.
FR-4 Handoff rules engine
Trigger handoff on low_confidence threshold, policy_flag, tool_error, sla_risk, uncertain_intent.
Map reason + required skills to a queue.
FR-5 Queues and routing
Create queues with required skills. Assignment: skills match first, round-robin fallback. Manual reassign and auto-escalation on SLA breach.
FR-6 Human inbox
Filter by queue, SLA status, priority, reason. Claim/accept/release assignments.
FR-7 Conversation view for humans
Full transcript with sender labels; context panel: risk flags, confidence, metadata.
Actions: reply, add note, resolve, return to agent, escalate.
FR-8 SLA management
Per-queue SLA targets for first response and resolution. Timers, color indicators, alerts.
FR-9 Audit and compliance
Immutable audit log for state changes, assignments, rules hits. Export transcript and audit as JSON/CSV.
FR-10 Analytics
Handoff rate by reason, time-to-first-response, time-to-resolution, SLA compliance, cost estimates (tokens + minutes), reopen rate.
FR-11 Admin settings
Manage users, roles, skills, queues, thresholds, API keys, webhooks, policy packs.
FR-12 API
Create conversation, append requester message, manual handoff, resolve. Webhooks: conversation.updated, message.created, handoff.created, assignment.created, conversation.resolved.
FR-13 Data residency “inside app”
SQLite database bundled with app. WAL mode. File-based cache/session. No external DB required.
FR-14 Return-to-agent loop
Human can return control to agent with notes that seed next agent run.
FR-15 Policy packs and redaction (MVP light)
Flag PII terms (regex list). Option to mask before storing. Store policy hits in metadata.
 Non-functional requirements (NFR)
 Performance: API P50 < 200 ms for CRUD; agent round-trip P50 < 6s (model dependent).
Concurrency: 50 simultaneous conversations on SQLite (WAL) without contention. Clear upgrade path to Postgres.
Availability: 99.5% single-node target for MVP.
Security: OWASP ASVS L2 baseline, salted hash passwords, HTTPS-only, secrets in env, role-based checks on every endpoint.
Privacy: PII masking option; configurable retention; GDPR delete.
Observability: Request logs, job logs, error tracking, basic metrics. Log rotation enabled.
 System architecture (implementation notes)
 Framework: Laravel 11 (PHP 8.3).
DB: SQLite (storage/database/database.sqlite) with WAL; migration-compatible with Postgres later.
Queues: Database driver; Horizon optional later.
Cache/Session: File.
LLM: OpenAI-compatible client. Model env-configurable (target gpt-5; fallback gpt-4o/4.1).
State machine: sebdesign/laravel-state-machine.
RBAC: spatie/laravel-permission.
Optional real-time later: beyondcode/laravel-websockets, Soketi, or Pusher.
 Data model (high level)
 users: id, name, email, password_hash, status, timezone.
roles, permissions (RBAC).
skills, user_skill(level).
conversations: id, subject, status, priority, requester_id, last_activity_at, metadata(json).
messages: id, conversation_id, sender_type(agent/human/system/requester), content, confidence, cost_usd, metadata(json), created_at.
handoffs: id, conversation_id, reason_code(enum), confidence, policy_hits(json), required_skills(json), created_at.
queues: id, name, skills_required(json), priority_policy(json).
queue_items: id, queue_id, conversation_id, enqueued_at.
assignments: id, conversation_id, user_id, assigned_at, accepted_at, released_at.
evaluations (QA): id, conversation_id, score, rubric(json), comments, created_by.
api_keys, webhooks, audits/events: standard fields including actor, entity, payload, created_at.
 Core workflows
 New conversation:
Requester sends message → conversation created (new) → transition to agent_working → Run Agent → agent responds or triggers handoff.
 Handoff:
Agent sets handoff=true with reason and required skills → create handoff → enqueue to queue → state to queued → human claims (assigned → human_working).
 Human actions:
Reply and resolve → resolved.
Reply and return to agent → back_to_agent → agent_working → agent continues.
 SLA:
Timers start on conversation creation and assignment; alerts on thresholds; escalate queue on breach.
 UI/UX requirements
 Design language: Clear, calm, “Right help, right now.” Accessible color contrast (WCAG AA).
Screens
Login and role-based redirect.
Human Inbox: filters (queue, SLA, priority, reason), list with badges and timers.
Conversation View: transcript timeline; right panel (context: confidence, policy flags, metadata, SLA).
Actions bar: Claim, Reply, Add internal note, Return to Agent, Resolve, Escalate.
Admin: Users, Roles, Skills, Queues, Rules (thresholds), API Keys, Webhooks.
Analytics: Hand-off rate, TTR, TTR by path (agent-only vs human), SLA, QA.
 Usability
Keyboard shortcuts for agents.
Inline risk callouts.
Confirm dialogs for resolve and return-to-agent.
 APIs (MVP)
 POST /api/conversations
Body: subject, metadata
Returns: conversation
 POST /api/conversations/{id}/messages
Body: content, sender=requester (fixed)
Effect: queues agent run
 POST /api/conversations/{id}/handoff
Body: reason_code, confidence, required_skills[]
 POST /api/conversations/{id}/resolve
Body: summary
 GET /api/queues/{id}/items
Query: status filters
 Webhooks (JSON payloads)
message.created, handoff.created, assignment.created, conversation.updated, conversation.resolved
 Auth: API keys (scoped). Rate limiting.
 Security and compliance
 RBAC with least privilege; per-action authorization.
Password hashing (bcrypt/argon2id), lockout on brute force, 2FA optional post-MVP.
CSRF for web; HTTPS required; secure cookies.
Sensitive columns encryption for API keys and webhook secrets.
Audit log of all state changes and admin actions.
PII masking rules: redact patterns before storage when enabled.
Export and delete (right to be forgotten).
 Analytics and reporting

 Objectives
- Give Supervisors, Admins, and Compliance Officers fast visibility into conversation health, SLA risk, cost, and policy adherence.
- Support daily operational standups (Supervisor focused), weekly business reviews (Admin + Leadership), and compliance audits.

 Core dashboards (MVP)
- **Operations overview:** Daily cards for open conversations, handoff count, SLA breaches, average wait times.
- **Routing & handoff:** Charts for handoff volume by reason/queue, agent vs. human resolution paths, and top triggering policies.
- **Responsiveness:** Time-to-first-response, time-to-human, and time-to-resolution percentiles with queue and priority filters.
- **Quality & compliance:** QA sample pass rate, reopen rate, policy hits per 100 conversations, unresolved escalations.
- **Cost insight:** Estimated token spend (LLM usage) and human handling minutes, with variance against baseline.

 UX requirements
- Filters: date range (default 7 days), queue, priority, policy reason, and agent/human owner.
- Visualization: mix of trend lines, stacked bars, and KPI cards using existing admin dashboard layout components.
- Drill-down: Clicking any chart segment opens filtered conversation list with transcript/audit quick links.
- Export controls: CSV and JSON exports respect current filters and append metadata (generated_at, requesting_user).
- Accessibility: Keyboard navigable, high-contrast color palette aligned with admin UI.

 Data & performance
- Aggregate queries run through AnalyticsService with caching (15-minute default) and per-tenant invalidation strategy.
- All metrics scoped by organization/environment and respect RBAC; Supervisors see queues they manage, Admins see all.
- Calculations sourced from conversations, handoffs, assignments, messages, SLA timers, QA evaluations, and audit logs.
- Ensure background jobs update summary tables nightly to guarantee export speeds < 5 seconds for 90-day range.

 Alerts & follow-up
- Flag SLA breach spikes (>20% day-over-day) and policy hit anomalies via admin notifications feed.
- Provide inline guidance links to relevant admin settings (queues, policies) when thresholds exceeded.
 Operational plan
 Deployment: Single container or VM. Persist storage/ directory for SQLite DB and logs.
Backups: Snapshot database.sqlite every hour; daily retention 30 days.
Monitoring: Health endpoint, error logs, queue worker watchdog.
Incident response: On SLA alert, auto-escalate to “Hot Queue”; email to Supervisor.
 Rollout plan
 Phase 1: Internal pilot with seeded data and 3–5 agents.
Phase 2: One real department, single queue, 2-week trial; collect metrics.
Phase 3: Broader rollout; add QA sampling and redaction; publish first transparency report.
 Assumptions and dependencies
 OpenAI API available; model name configurable (use GPT-5 when released).
SQLite sufficient for MVP load; can switch to Postgres with no schema change.
Single channel (web API) for MVP.
No external real-time push required at MVP.
 Out of scope (for MVP)
 Voice channel, SMS, social DMs.
Complex workflow builder UI.
Contract-based pricing and billing.
Native mobile apps.
 Risks and mitigations
 Model variability: Enforce JSON output contract; fallback to safe handoff on parse errors.
SQLite contention: Enable WAL; keep write transactions short; scale to Postgres if needed.
Policy misses: Start with conservative thresholds; expand policy packs with QA feedback.
Adoption: Provide clear agent UX, macros, and training; track value with analytics.
 Open questions
 Will you run single-tenant internal or plan for SaaS later?
Must we support attachments at MVP?
Any industry compliance targets (HIPAA, SOC 2) from day one?
Do you need scheduling/working hours for SLAs?
Preferred agent response tone and length constraints?
 Acceptance criteria (MVP)
 A requester can create a conversation and receive an AI response within 10 seconds median.
If confidence < threshold or policy flag true, the conversation moves to a human queue automatically.
A human can claim, reply, return to agent, and resolve; all state changes audited.
SLA timers visible and update in real time; breaches trigger visible alerts.
Admin can create users, roles, skills, queues; set thresholds; create API keys and webhooks.
Analytics show handoff rate, TTR, and SLA compliance by queue for a chosen date range.
All data persists in the embedded database; backup script runs and is verified.

 Implementation status & next steps (Nov 2025)

| Requirement | Current status | Key gaps | Immediate next actions |
| --- | --- | --- | --- |
| FR-3 Agent runner & FR-14 Return-to-agent | **Not started** | No OpenAI integration, no schema enforcement, return-to-agent transition does not trigger agent run. | Finalize contract, implement agent runner service/job, wire into state machine and return-to-agent flow, add regression tests. |
| FR-4 Handoff rules engine | **Not started** | No configurable thresholds or rules table; handoff endpoint trusts caller payload. | Design rules data model + admin UI, build evaluation service invoked pre-handoff, add tests. |
| FR-8 SLA management | **Not started** | SLA fields unused; no timers, breach detection, or UI indicators. | Implement timers/queue monitors, surface SLA badges in inbox, create alerting hooks. |
| FR-9 Audit exports | **Not started** | Audit table lacks export endpoints/utilities. | Build JSON/CSV export endpoints with filters, add download UI + authorization. |
| FR-10 Analytics & reporting acceptance criteria | **Not started** | Dashboard placeholder; no metrics queries or charts. | Define reporting queries, expose API, render charts for handoff rate, TTR, SLA compliance. |
| FR-11 Admin settings coverage | **Not started** | No UI/API for thresholds, webhooks, policy packs beyond CRUD. | Extend admin settings pages + endpoints for guardrails, webhooks, skills, policy packs. |
| FR-12 Webhooks | **Not started** | No event dispatch for message/handoff/assignment updates. | Implement webhook subscription model + queued dispatchers, add delivery retries + tests. |
| FR-15 Policy packs & redaction | **Not started** | No regex policy storage or masking before persistence. | Create policy store, integrate redaction pipeline before saving content, log policy hits. |
| Observability / monitoring (NFR) | **Not started** | No health endpoint, metrics, or log rotation config. | Add `/health` endpoint, structured logging/rotation, basic queue & SLA metrics. |

 Future enhancements
 Multi-tenant SaaS with per-tenant isolation and billing.
Channels: Email, Slack, Zendesk, Intercom, SMS.
Advanced QA: rubric builder, calibration, double-blind review.
Generative KB suggestions and article publishing.
Real-time websockets, presence, and co-pilot for humans.
Human workforce scheduling and forecast-based routing.
Fine-tuned safety classifiers and semantic policy detection.
Redaction UI and privacy vault.
 Glossary
 Handshake: The moment control passes between AI and human.
Handoff: The act and record of passing a case to a human.
SLA: Service Level Agreement for response/resolution time.
RBQM: Risk-Based Quality Management for dynamic QA sampling.
 Implementation note
Model target: OPENAI_MODEL=gpt-5 when available; default to gpt-5-mini. 
Database inside the app: SQLite with WAL; nightly export. Clear path to Postgres via env switch.
 This PRD gives you the what and why. If you want, I can also deliver a companion Technical Design Doc with exact migrations, endpoints, state diagrams, and a week-by-week build plan mapped to these requirements.