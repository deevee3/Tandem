# Task List Management

Guidelines for managing task lists in markdown files to track progress on completing a PRD

## Task Implementation
- **One sub-task at a time:** Do **NOT** start the next sub‑task until you ask the user for permission and they say "yes" or "y"
- **Completion protocol:**  
  1. When you finish a **sub‑task**, immediately mark it as completed by changing `[ ]` to `[x]`.
  2. If **all** subtasks underneath a parent task are now `[x]`, follow this sequence:
    - **First**: Run the full test suite (`pytest`, `npm test`, `bin/rails test`, etc.)
    - **Only if all tests pass**: Stage changes (`git add .`)
    - **Clean up**: Remove any temporary files and temporary code before committing
    - **Commit**: Use a descriptive commit message that:
      - Uses conventional commit format (`feat:`, `fix:`, `refactor:`, etc.)
      - Summarizes what was accomplished in the parent task
      - Lists key changes and additions
      - References the task number and PRD context
      - **Formats the message as a single-line command using `-m` flags**, e.g.:

        ```
        git commit -m "feat: add payment validation logic" -m "- Validates card type and expiry" -m "- Adds unit tests for edge cases" -m "Related to T123 in PRD"
        ```
  3. Once all the subtasks are marked completed and changes have been committed, mark the **parent task** as completed.
- Stop after each sub‑task and wait for the user's go‑ahead.

## Task List Maintenance

1. **Update the task list as you work:**
   - Mark tasks and subtasks as completed (`[x]`) per the protocol above.
   - Add new tasks as they emerge.

2. **Maintain the "Relevant Files" section:**
   - List every file created or modified.
   - Give each file a one‑line description of its purpose.

## AI Instructions

When working with task lists, the AI must:

1. Regularly update the task list file after finishing any significant work.
2. Follow the completion protocol:
   - Mark each finished **sub‑task** `[x]`.
   - Mark the **parent task** `[x]` once **all** its subtasks are `[x]`.
3. Add newly discovered tasks.
4. Keep "Relevant Files" accurate and up to date.
5. Before starting work, check which sub‑task is next.
6. After implementing a sub-task, update the file and then pause for user approval.

## Current Task Progress

### Completed
- [x] Wire `RolePermission` pivot into Role/Permission relations for cache invalidation
- [x] Ensure fallback hand-off persists a handoff record when fallback branch executes
- [x] Agent runner integration (FR-3)
- [x] Handoff rules engine (FR-4)
- [x] Basic conversation lifecycle (FR-2)
- [x] Human inbox UI (FR-6)
- [x] Queue and assignment models (FR-5 partial)

### Critical Priority (Blocks MVP)

#### FR-11: Admin Settings UI
- [ ] Create admin layout and navigation
  - [x] Build admin sidebar with sections
  - [x] Add admin route group with authorization
  - [x] Create admin dashboard landing page
- [x] Users & Roles Management
  - [x] Build users CRUD UI
  - [x] Build roles CRUD UI
  - [x] Add role-permission assignment UI
  - [x] Add user-role assignment UI
- [x] Skills Management
  - [x] Build skills CRUD UI
  - [x] Add user-skill assignment UI with levels
- [ ] Queues Management
  - [x] Build queues CRUD UI
  - [x] Add queue-skill requirements configuration
  - [ ] Add SLA configuration fields (first_response, resolution)
  - [ ] Add priority policy configuration
- [ ] Handoff Policies Management
  - [ ] Build handoff policies CRUD UI
    - [ ] Audit existing `HandoffPolicy`/`HandoffPolicyRule` models, migrations, and seeders for required fields and relationships.
    - [x] Implement admin controller, routes, and JSON resources for list/create/update/delete with form request validation.
    - [x] Create Inertia page with table view, create/edit dialogs, active toggle, and metadata display consistent with other admin pages.
    - [x] Add feature tests covering authorization, validation errors, and CRUD flows.
  - [ ] Add policy rules editor (triggers, criteria, priority)
    - [ ] Support trigger types (confidence_below_threshold, policy_flag_detected, tool_error, agent_requested_handoff) with trigger-specific criteria inputs.
    - [ ] Provide UI for priority ordering, enable/disable toggles, and inline validation feedback.
    - [ ] Persist rule collections through dedicated controller endpoints and sync logic on the policy model.
    - [ ] Extend `PolicyEvaluationService` tests to confirm rule evaluation honors new criteria and priority metadata.
  - [ ] Add policy-skill assignment UI
    - [ ] Reuse queues skill selector component to avoid duplication and ensure consistent UX.
    - [ ] Persist skill assignments via pivot sync in controller and expose them in API resource payloads.
    - [ ] Add regression tests verifying policy-required skills appear in UI and feed routing decisions.
  - [ ] Add threshold configuration
    - [ ] Capture per-policy confidence thresholds with numeric validation and sensible defaults.
    - [ ] Expose threshold inputs and policy metadata summary in the admin UI.
    - [ ] Ensure evaluation logic and seed data respect editable thresholds; update seeders if necessary.
- [ ] API Keys Management
  - [ ] Build API keys listing UI
  - [ ] Add create/revoke API key UI
  - [ ] Add scopes configuration
  - [ ] Add expiration settings
- [ ] Webhooks Management
  - [ ] Build webhooks CRUD UI
  - [ ] Add event subscription selector
  - [ ] Add secret generation
  - [ ] Add webhook testing UI

#### FR-8: SLA Management
- [ ] Database & Models
  - [ ] Add SLA fields to queues table migration
  - [ ] Update Queue model with SLA accessors
  - [ ] Add SLA status to queue_items
- [ ] SLA Timer Service
  - [ ] Create SLATimerService for calculations
  - [ ] Add first_response_sla timer logic
  - [ ] Add resolution_sla timer logic
  - [ ] Add time_remaining calculations
- [ ] Breach Detection
  - [ ] Create SLA breach detection job
  - [ ] Add breach status to queue items
  - [ ] Implement auto-escalation on breach
  - [ ] Add breach alerts/notifications
- [ ] UI Integration
  - [ ] Add SLA badges to inbox queue items
  - [ ] Add color indicators (green/yellow/red)
  - [ ] Add SLA countdown timers
  - [ ] Add breach warnings in conversation view
  - [ ] Add SLA filters to inbox

#### FR-10: Analytics & Reporting
- [ ] Analytics Service
  - [ ] Create AnalyticsService class
  - [ ] Build handoff rate query (by reason, by queue)
  - [ ] Build time-to-first-response query
  - [ ] Build time-to-resolution query
  - [ ] Build SLA compliance query (by queue, by priority)
  - [ ] Build cost estimation query (tokens + human minutes)
  - [ ] Build reopen rate query
- [ ] API Endpoints
  - [ ] Create analytics API controller
  - [ ] Add GET /api/analytics/handoff-rate endpoint
  - [ ] Add GET /api/analytics/response-times endpoint
  - [ ] Add GET /api/analytics/sla-compliance endpoint
  - [ ] Add GET /api/analytics/costs endpoint
  - [ ] Add date range filtering
  - [ ] Add export to CSV/JSON
- [ ] Dashboard UI
  - [ ] Create analytics/dashboard page
  - [ ] Add handoff rate chart
  - [ ] Add response time charts
  - [ ] Add SLA compliance chart
  - [ ] Add cost summary cards
  - [ ] Add drill-down to conversations
  - [ ] Add date range picker

#### FR-7: Conversation View Completion
- [ ] Message Timeline
  - [ ] Enhance message display with proper formatting
  - [ ] Add sender type badges
  - [ ] Add timestamp formatting
  - [ ] Add confidence scores to agent messages
  - [ ] Add policy flags display
- [ ] Context Panel
  - [ ] Create context sidebar component
  - [ ] Add risk indicators section
  - [ ] Add confidence score display
  - [ ] Add policy flags section
  - [ ] Add handoff metadata display
  - [ ] Add SLA status display
  - [ ] Add assignment history
- [ ] Actions
  - [ ] Add internal notes functionality
  - [ ] Add escalate action
  - [ ] Add reassign action
  - [ ] Improve return-to-agent with notes field

### High Priority (MVP Features)

#### FR-9: Audit & Compliance Exports
- [ ] Export Service
  - [ ] Create AuditExportService
  - [ ] Add JSON export formatter
  - [ ] Add CSV export formatter
  - [ ] Add date range filtering
  - [ ] Add event type filtering
- [ ] API Endpoints
  - [ ] Add GET /api/audit/export endpoint
  - [ ] Add GET /api/conversations/{id}/transcript endpoint
  - [ ] Add authorization checks
- [ ] Admin UI
  - [ ] Add audit log viewer page
  - [ ] Add export button with format selector
  - [ ] Add filter controls
  - [ ] Add download functionality

#### FR-12: Webhooks Dispatch System
- [ ] Webhook Service
  - [ ] Create WebhookDispatchService
  - [ ] Add HMAC signature generation
  - [ ] Add payload formatting
- [ ] Event Listeners
  - [ ] Create MessageCreated event & listener
  - [ ] Create HandoffCreated event & listener
  - [ ] Create AssignmentCreated event & listener
  - [ ] Create ConversationUpdated event & listener
  - [ ] Create ConversationResolved event & listener
- [ ] Delivery System
  - [ ] Create DispatchWebhook job
  - [ ] Add retry logic with exponential backoff
  - [ ] Add delivery status tracking
  - [ ] Add webhook delivery logs table
- [ ] Testing
  - [ ] Add webhook test endpoint
  - [ ] Add delivery verification

#### FR-15: Policy Packs & Redaction
- [ ] Policy Pack Storage
  - [ ] Create policy_packs table migration
  - [ ] Create PolicyPack model
  - [ ] Add regex pattern storage
  - [ ] Add PII pattern presets
- [ ] PII Detection Service
  - [ ] Create PIIDetectionService
  - [ ] Add regex-based detection
  - [ ] Add pattern matching logic
  - [ ] Add configurable patterns
- [ ] Redaction Pipeline
  - [ ] Create RedactionService
  - [ ] Add masking logic (before message save)
  - [ ] Add policy hit logging
  - [ ] Integrate into message creation flow
- [ ] Admin UI
  - [ ] Build policy packs CRUD UI
  - [ ] Add pattern editor
  - [ ] Add test/preview functionality
  - [ ] Add redaction configuration

#### FR-5: Skills-Based Routing Completion
- [ ] Routing Logic
  - [ ] Enhance queue resolution to match required skills
  - [ ] Add user skill level matching
  - [ ] Add workload balancing
  - [ ] Add manual reassignment
- [ ] Testing
  - [ ] Add skills routing tests
  - [ ] Add edge case tests (no matching skills)

### Medium Priority (Operational)

#### NFR: Observability
- [ ] Health Endpoint
  - [ ] Create /health endpoint
  - [ ] Add database connectivity check
  - [ ] Add queue worker check
  - [ ] Add disk space check
- [ ] Monitoring
  - [ ] Configure log rotation
  - [ ] Add queue worker watchdog
  - [ ] Add basic metrics collection
  - [ ] Add error tracking integration (optional)
- [ ] Logging
  - [ ] Add structured logging
  - [ ] Add request ID tracking
  - [ ] Add performance logging

#### FR-1: RBAC Completion
- [ ] Permission Definitions
  - [ ] Define all required permissions
  - [ ] Add permission seeder
  - [ ] Add role seeder with default permissions
- [ ] UI Restrictions
  - [ ] Add role-based menu visibility
  - [ ] Add permission-based action buttons
  - [ ] Add admin guard enforcement

### Testing & Documentation
- [ ] Re-run full automated test suite
- [ ] Add integration tests for new features
- [ ] Update README with new features
- [ ] Create deployment guide
- [ ] Create admin user guide

### Relevant Files
- `Shovel/app/Models/Pivots/RolePermission.php` – Custom pivot model flushing role/permission caches on save/delete events
- `Shovel/app/Jobs/RunAgentForConversation.php` – Ensures fallback path records handoffs before queue resolution and timestamps transitions
- `Shovel/app/Services/AgentRunnerService.php` – OpenAI integration with JSON schema enforcement
- `Shovel/app/Services/PolicyEvaluationService.php` – Handoff rules engine with policy matching
- `Shovel/app/Services/ConversationLifecycleService.php` – State machine callbacks for conversation lifecycle
- `Shovel/resources/js/pages/inbox.tsx` – Human inbox UI with queue filtering and claiming
- `Shovel/resources/js/pages/conversation.tsx` – Conversation view with return-to-agent and resolve actions
- `Shovel/routes/admin.php` – Admin route definitions with authorization middleware and users API endpoints
- `Shovel/resources/js/components/admin-sidebar.tsx` – Admin navigation sidebar with nested menu items
- `Shovel/resources/js/components/nav-admin.tsx` – Admin navigation component supporting collapsible sub-items
- `Shovel/resources/js/layouts/admin-layout.tsx` – Admin layout wrapper component
- `Shovel/resources/js/layouts/admin/admin-sidebar-layout.tsx` – Admin sidebar layout template
- `Shovel/resources/js/pages/admin/dashboard.tsx` – Admin dashboard landing page with section cards
- `Shovel/resources/js/types/index.d.ts` – Updated NavItem type to support nested navigation items
- `Shovel/app/Http/Controllers/Admin/UserController.php` – Users CRUD API controller with search and pagination
- `Shovel/app/Http/Resources/UserResource.php` – User API resource with roles and skills relationships
- `Shovel/resources/js/pages/admin/users/index.tsx` – Users management UI with table, search, and CRUD dialogs
- `Shovel/resources/js/components/ui/table.tsx` – Reusable table component for admin interfaces
- `Shovel/app/Http/Controllers/Admin/RoleController.php` – Roles CRUD API controller with search, pagination, permission sync, and /all endpoint
- `Shovel/app/Http/Resources/RoleResource.php` – Role API resource with permissions relationships
- `Shovel/resources/js/pages/admin/roles/index.tsx` – Roles management UI with table, search, and CRUD dialogs
- `Shovel/app/Http/Controllers/Admin/SkillController.php` – Skills CRUD API controller with search, pagination, and /all endpoint
- `Shovel/app/Http/Resources/SkillResource.php` – Skill API resource for consistent JSON responses
- `Shovel/app/Http/Controllers/Admin/UserController::syncSkills()` – User-skill sync endpoint with level support (1-5)
- `Shovel/resources/js/pages/admin/users/index.tsx` – Enhanced users UI with role checkboxes in create/edit dialogs and skills management dialog with Award button
- `Shovel/resources/js/pages/admin/skills/index.tsx` – Skills management UI with table, search, and CRUD dialogs for name and description
- `Shovel/app/Http/Controllers/Admin/QueueController.php` – Queues CRUD API controller with search, pagination, default queue management, and skill requirements
- `Shovel/app/Http/Resources/QueueResource.php` – Queue API resource for consistent JSON responses
- `Shovel/resources/js/pages/admin/queues/index.tsx` – Queues management UI with table, search, CRUD dialogs, default queue indicator, and skill requirements checkboxes
- `Shovel/tests/Feature/QueueCrudTest.php` – Comprehensive test suite for queues CRUD operations (9 tests)