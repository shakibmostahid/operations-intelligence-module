# OpsPulse Implementation Roadmap

OpsPulse is a small operations intelligence and SLA monitoring dashboard for a take-home test project. The goal is to build a complete but focused Laravel application where users can monitor incidents, assign owners, escalate and resolve issues, track SLA health, add comments, review activity history, and generate a deterministic local AI-style operational summary.

This roadmap is intentionally implementation-focused, but it does not include generated application code.

## Scope Guardrails

- Keep the project small, clean, and reviewer-friendly.
- Use Laravel 12 or the latest stable Laravel, PHP 8.5, MySQL, Blade, Livewire, Tailwind CSS, Docker Compose, and PHPUnit or Pest.
- Do not use an external AI API. The operational summary should be generated locally with deterministic rules.
- Do not add queues, Redis, Horizon, websockets, or production infrastructure unless the take-home requirements change.
- Avoid complex authentication and authorization. Use Laravel defaults only if needed for seeded users and basic reviewer flows.
- Prefer simple service classes and focused tests over broad abstractions.

## 1. Docker Setup

- Keep Docker-related files under the `docker/` directory where practical.
- Keep `docker-compose.yml` at the project root because that is the standard Compose entrypoint.
- Configure the root `docker-compose.yml` to reference Docker files inside `docker/`.
- Suggested structure:

```text
docker/
  app/
    Dockerfile
    php.ini
  mysql/
    init.sql
docker-compose.yml
```

- Add an `app` service for PHP 8.5 with required Laravel extensions such as `pdo_mysql`, `mbstring`, `bcmath`, `zip`, and `intl`.
- Add a `mysql` service with a named volume for database persistence.
- Expose the Laravel app port, for example `8000`.
- Configure environment variables for database name, user, password, host, and port.
- Keep the Docker setup development-oriented and easy to run locally.

## 2. Laravel Project Initialization

- Initialize Laravel 12 or the latest stable Laravel inside the application source directory.
- Install and configure Livewire.
- Configure Tailwind CSS using Laravel's standard frontend tooling.
- Confirm the Laravel welcome page or a temporary dashboard route loads through Docker.
- Create a minimal application shell with Blade:
  - main layout
  - top navigation
  - dashboard route placeholder
- Keep the initial scaffold close to Laravel conventions so reviewers can navigate quickly.

## 3. Database Connection

- Configure Laravel to use MySQL.
- Use these expected local environment defaults:
  - `DB_CONNECTION=mysql`
  - `DB_HOST=mysql`
  - `DB_PORT=3306`
  - `DB_DATABASE=opspulse`
  - `DB_USERNAME=opspulse`
  - `DB_PASSWORD=opspulse`
- Verify the application can connect to MySQL from inside Docker.
- Run Laravel's default migrations successfully before adding domain tables.
- Document common database setup commands later in the README.

## 4. Laravel Migrations and Models

- Create a compact incident domain model:
  - `users`
  - `incidents`
  - `incident_comments`
  - `incident_activities`
- Add `incidents` fields for:
  - title
  - description
  - status: `open`, `investigating`, `escalated`, `resolved`
  - priority: `low`, `medium`, `high`, `critical`
  - owner user
  - reporter user
  - SLA due timestamp
  - resolved timestamp
  - escalated timestamp
- Add `incident_comments` fields for:
  - incident
  - user
  - body
- Add `incident_activities` fields for:
  - incident
  - nullable user
  - type
  - message
  - nullable JSON metadata
- Define Eloquent relationships:
  - incident belongs to owner
  - incident belongs to reporter
  - incident has many comments
  - incident has many activities
  - comment belongs to incident and user
  - activity belongs to incident and optionally user
- Use either PHP enums or model-level constants for statuses and priorities. Keep the choice consistent across validation, services, UI filters, and tests.

## 5. Seed Data

- Seed a small set of realistic users:
  - operations lead
  - support engineer
  - incident commander
- Seed 8 to 12 incidents that cover the main reviewer scenarios:
  - open incidents
  - investigating incidents
  - escalated incidents
  - resolved incidents
  - low, medium, high, and critical priorities
  - healthy SLA state
  - at-risk SLA state
  - breached SLA state
- Seed comments that make incident detail pages feel realistic.
- Seed activity records for assignment, escalation, status changes, comments, and resolution.
- Keep seeded data deterministic so screenshots, tests, and reviewer expectations stay stable.

## 6. Services

- Add small service classes for business behavior instead of placing all logic in Livewire components.
- Add an `IncidentStatusService` responsible for:
  - assigning an owner
  - escalating an incident
  - resolving an incident
  - creating activity records for state changes
- Add an `SlaService` responsible for:
  - calculating SLA state: `healthy`, `at_risk`, `breached`, `resolved`
  - counting breached incidents
  - identifying high-risk operational work
- Add an `OperationalSummaryService` responsible for:
  - generating a local AI-style summary
  - highlighting critical incidents, breached SLAs, escalations, and workload distribution
  - returning deterministic text based on current incident data
- Keep services independent from Livewire so they are easy to unit test.

## 7. UI Pages and Components

- Build a practical operations dashboard using Blade, Livewire, and Tailwind CSS.
- Create a dashboard page with:
  - incident counts by status
  - SLA breached count
  - high and critical open incidents
  - deterministic operational summary panel
- Create an incident list page with:
  - search by title or description
  - filters for status, priority, owner, and SLA state
  - a readable incident table
  - clear badges for status, priority, and SLA health
- Create an incident detail page with:
  - incident metadata
  - owner assignment control
  - escalation action
  - resolution action
  - comments
  - activity timeline
- Suggested Livewire components:
  - incident list and filters
  - incident detail actions
  - comment form
  - operational summary panel
- Keep the interface dense, readable, and work-focused. Avoid marketing-style layouts, oversized hero sections, and decorative UI that does not help the reviewer understand the product.

## 8. Tests

- Use PHPUnit or Pest consistently.
- Add service tests for:
  - healthy SLA calculation
  - at-risk SLA calculation
  - breached SLA calculation
  - resolved SLA calculation
  - assigning an owner creates activity
  - escalating an incident updates status and timestamp
  - resolving an incident updates status and timestamp
  - operational summary includes critical signals
- Add feature tests for:
  - dashboard page loads
  - incident list page loads
  - incident detail page loads
  - user can add a comment
  - user can assign an owner
  - user can escalate an incident
  - user can resolve an incident
- Prefer factories for test setup.
- Avoid brittle visual or snapshot tests.

## 9. README

- Update the README after implementation with:
  - project name and short description
  - selected take-home option
  - core feature list
  - local setup instructions
  - Docker commands
  - migration and seeding commands
  - frontend build commands
  - test commands
- Add reviewer notes explaining:
  - the operational summary is deterministic and local
  - no external AI API is required
  - seed data includes demo operational scenarios
  - the scope is intentionally compact
- Add a brief architecture overview covering:
  - models
  - services
  - Livewire components
  - SLA calculation approach

## Manual Verification for This Roadmap

- Confirm `docs/ROADMAP.md` exists.
- Confirm the roadmap includes all requested steps from Docker setup through README.
- Confirm Docker guidance places build and config files under `docker/`.
- Confirm the document does not implement Laravel code, Docker files, migrations, services, UI, or tests.
