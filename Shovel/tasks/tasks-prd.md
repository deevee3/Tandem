## Relevant Files

- `Shovel/database/migrations/2025_11_03_000100_create_roles_and_permissions_tables.php` - Defines core RBAC tables to align with new models and policies.
- `Shovel/database/seeders/CoreDatabaseSeeder.php` - Seeds baseline roles, permissions, and admin user assignments.
- `Shovel/app/Models/User.php` - User relationships and helper methods for roles/permissions.
- `Shovel/app/Models/Role.php` - Role relationships and permission bridging.
- `Shovel/app/Models/Permission.php` - Permission relationships used by roles and policies.
- `Shovel/app/Http/Middleware` - Location for new admin-permission middleware alias.
- `Shovel/routes/web.php` - Web routes entry point for admin settings grouping.
- `Shovel/app/Http/Controllers/Settings` - Namespace for new admin settings controllers.
- `Shovel/resources/js/pages/settings` - Inertia pages for admin settings UI.
- `Shovel/tests/Feature` - Feature tests covering admin routes and authorization.

### Notes

- Update this list as new files are touched or introduced during implementation.

## Tasks

- [ ] 1.0 Establish role and permission domain models aligned with existing migrations and seeding.
  - [x] 1.1 Audit existing RBAC migrations and config to confirm alignment with PRD roles (Admin, Human Agent, Supervisor).
  - [x] 1.2 Update or add migrations to ensure roles, permissions, and pivot tables match required relationships.
  - [ ] 1.3 Implement Role and Permission Eloquent models with relationships, scopes, and caching per package conventions.
  - [ ] 1.4 Extend `User` model helpers for role/permission checks needed across web and API layers.
- [ ] 2.0 Enforce admin authorization via middleware/policies and secure route grouping.
  - [ ] 2.1 Inventory existing routes/controllers to determine required admin protection points.
  - [ ] 2.2 Register middleware aliases or guards that gate admin settings behind RBAC checks.
  - [ ] 2.3 Apply middleware/policies to admin routes and controllers, including Inertia and API endpoints.
  - [ ] 2.4 Add unauthorized handling (redirects/messages) consistent with existing UX patterns.
- [ ] 3.0 Implement admin settings backend for user, role, and queue management workflows.
  - [ ] 3.1 Design controller structure/services for user, role, and queue management per FR-11 requirements.
  - [ ] 3.2 Implement user management actions (list/create/update/deactivate, role assignment) with validation.
  - [ ] 3.3 Implement role and permission CRUD endpoints with auditing hooks.
  - [ ] 3.4 Implement queue CRUD endpoints including skills, SLA thresholds, and routing metadata.
  - [ ] 3.5 Expose backend responses/resources compatible with Inertia front-end consumption.
- [ ] 4.0 Build Inertia-based admin settings UI for managing users, roles, and queues.
  - [ ] 4.1 Review existing Inertia layouts/components to align UI patterns and navigation.
  - [ ] 4.2 Implement Settings landing page with navigation tabs/links for users, roles, queues.
  - [ ] 4.3 Build user management screens (table, forms, role assignment) with loading/error states.
  - [ ] 4.4 Build role/permission management screens supporting CRUD operations.
  - [ ] 4.5 Build queue management UI with SLA indicators, skill selection, and routing details.
  - [ ] 4.6 Wire front-end actions to backend endpoints and handle success/error feedback.
- [ ] 5.0 Expand seeds, documentation, and automated tests for new admin capabilities.
  - [ ] 5.1 Update database seeders to create baseline roles, permissions, admin user, and default queues.
  - [ ] 5.2 Write feature tests covering admin authorization, user management, and queue flows.
  - [ ] 5.3 Add unit/integration tests for role/permission helpers and routing logic.
  - [ ] 5.4 Update README and docs with admin setup instructions, roles matrix, and testing guidance.
  - [ ] 5.5 Ensure task list and relevant files sections stay current as implementation progresses.
