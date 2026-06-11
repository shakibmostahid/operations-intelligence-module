# Incident & Operations Tracking

## Summary

Incident & Operations Tracking is an operations intelligence module for teams that need one place to understand active incidents, ownership, urgency, SLA exposure, and response history.

It addresses fragmented operational reporting by providing a shared incident queue, dashboard metrics, role-based actions, escalation tracking, alert routing, and deterministic AI-style summaries without relying on external services.

## Features

- Operational dashboard with incident, SLA, uptime, tag, severity, status, and trend metrics
- Incident creation, assignment, filtering, pagination, status changes, escalation, resolution, comments, and RCA notes
- SLA health detection with breached and at-risk incident views
- Immutable activity timeline for incident changes and comments
- Role-based user management and incident permissions
- Mock alert routing based on incident creation, escalation, and SLA breach events
- Idempotent mock webhook ingestion
- CSV incident-list export and PDF incident-detail export
- Deterministic mock AI operational summary with a suggested next action
- JSON request logging with request ID, user ID, IP address, user agent, status, and duration

## Technologies

- PHP 8.5
- Laravel 13
- Vue 3
- Vite
- Tailwind CSS 4
- Nginx
- MySQL 8.4
- Docker Compose
- PHPUnit
- Playwright

## Requirements

- Docker Desktop or Docker Engine
- Docker Compose
- Available local ports:
  - `8000` for Nginx
  - `5173` for Vite development assets
  - `3307` for MySQL host access

No host installation of PHP, Composer, Node.js, or MySQL is required.

## Quick Start

Clone the public repository and enter the Docker project:

```bash
git clone <your-public-repo-url>
cd <repo>
cd docker
```

Create the local environment files:

```bash
cp .env.example .env
cp envs/app.env.example envs/app.env
cp envs/mysql.env.example envs/mysql.env
cp envs/nginx.env.example envs/nginx.env
```

Generate an application key and add the returned value to `envs/app.env`:

```bash
docker compose run --rm app php artisan key:generate --show
```

Start the complete application:

```bash
docker compose up --build
```

In another terminal, initialize the database:

```bash
cd <repo>/docker
docker compose exec app php artisan migrate --seed
```

Open `http://localhost:8000`.

## Initial Setup

Run commands from the `docker/` directory:

```bash
cp .env.example .env
cp envs/app.env.example envs/app.env
cp envs/mysql.env.example envs/mysql.env
cp envs/nginx.env.example envs/nginx.env
```

Generate an application key:

```bash
docker compose run --rm app php artisan key:generate --show
```

Place the generated key in `envs/app.env`:

```env
APP_KEY=base64:generated-value
```

Build and start the services:

```bash
docker compose up -d --build
```

Create and seed the database:

```bash
docker compose exec app php artisan migrate --seed
```

Open the application at:

```text
http://localhost:8000
```

## Running The Project

Start:

```bash
cd docker
docker compose up -d
```

View service status and logs:

```bash
docker compose ps
docker compose logs -f
```

Stop:

```bash
docker compose down
```

Rebuild after Dockerfile or dependency changes:

```bash
docker compose up -d --build
```

## Demo Accounts

| Role | Email | Password | Behavior |
| --- | --- | --- | --- |
| Super Admin | `super.admin@iot.com` | `incident@admin` | Ready to use |
| Admin | `admin@iot.com` | `password` | Must change password |
| Support Engineer | `support@iot.com` | `password` | Must change password |
| Viewer | `viewer@iot.com` | `password` | Must change password |

## Roles And Permissions

- `super_admin`: manages users and can change any active incident status.
- `admin`: creates lower-role users and manages lower-role accounts.
- `support_engineer`: works on incidents and comments.
- `viewer`: read-only access to operational data.

Incident status can be changed only by the incident creator, assigned user, or super admin. Assignment is fixed after creation. Escalation requires a reason. Resolved incidents are locked, while non-viewers may continue adding comments.

## Authentication

- Laravel web authentication uses database-backed sessions.
- Only active accounts may sign in.
- Temporary-password users are redirected to the password-change page.
- A new password cannot match the current password.
- Successful login regenerates the session ID.
- Deactivating a user removes their active database sessions.
- Session lifetime is 120 minutes of inactivity.

## Incident Workflow

Incidents begin with `open` status and support:

```text
open
investigating
escalated
resolved
```

Status changes may move forward or backward before resolution. Escalations create timeline activity and trigger matching alert rules. Resolution records the completion time and locks incident details.

The incident list supports search and filtering by severity, status, assigned user, tag, SLA state, and creation date. It also supports ID sorting, selectable page sizes, CSV export, and direct navigation to incident details.

## Dashboard

The dashboard includes:

- Total, critical, and escalated incident metrics
- Severity or status distribution chart
- Incident counts by tag
- Created-versus-resolved trend chart
- Simulated service uptime metrics
- Current user’s unresolved assigned incidents
- Paginated unresolved SLA breaches
- Routed in-app alerts

Dashboard metrics support predefined and custom date ranges.

## Mock AI Summary

Incident details request an operational summary from:

```text
GET /incidents/{incident}/operational-summary
```

The authenticated endpoint combines severity, status, SLA state, assignment, tags, and timeline activity. The page displays a generating animation before rendering the summary and suggested next action.

Responses are deterministic and loaded from:

```text
codes/resources/mocks/ai-operational-summary.json
```

No external AI API or API key is required.

## External Services

The local project does not depend on any external API:

- AI summaries use the local mock API and JSON response combinations.
- Webhook ingestion is exposed as a local authenticated endpoint.
- Alert routing creates local database-backed alerts.
- Email uses Laravel's log mailer by default.

These mocks and local alternatives allow the complete workflow to run without third-party accounts, credentials, or network services.

## Mock Webhook

Configure `INCIDENT_WEBHOOK_TOKEN` in `docker/envs/app.env`, then submit an incident:

```bash
curl -X POST http://localhost:8000/api/webhooks/incidents \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Token: your-configured-token' \
  -d '{
    "external_id": "MON-1042",
    "source": "monitoring",
    "title": "Checkout API unavailable",
    "description": "Health checks failed five times.",
    "severity": "critical",
    "sla_deadline": "2026-06-11 12:00:00"
  }'
```

The combination of `source` and `external_id` makes webhook delivery idempotent.

## Alert Routing

Seeded alert rules create in-app alerts for:

- Critical incident creation
- Incident escalation
- SLA breach

Recipients are selected from configured roles and the incident’s assigned user.

## Exports

- Incident lists can be exported as CSV with current filters applied.
- Individual incidents can be exported as PDF with details and timeline data.

## Logging

Application logs are written to the container console by default. HTTP request logs use JSON and include:

- Request ID
- Method, route, path, and response status
- Authenticated user ID
- IP address and user agent
- Request and response sizes
- Execution duration

View live application and request logs with:

```bash
cd docker
docker compose logs -f app
```

## Project Structure

```text
codes/
  app/                 Controllers, middleware, models, and services
  database/            Migrations, factories, and seeders
  resources/js/        Vue pages and components
  resources/mocks/     Mock AI response combinations
  routes/              Web and API routes

docker/
  configs/             PHP and Nginx configuration
  entrypoints/         Container startup scripts
  envs/                Service environment files and examples
  Dockerfile           PHP-FPM application image
  Nginx.Dockerfile     Nginx and production asset image
  docker-compose.yml   Base services
  docker-compose.dev.yml Development overrides

docs/ROADMAP.md        Original implementation roadmap
```

## Useful Commands

```bash
# Run migrations
docker compose exec app php artisan migrate

# Rebuild demo data
docker compose exec app php artisan migrate:fresh --seed

# Clear Laravel caches
docker compose exec app php artisan optimize:clear

# Run tests
docker compose exec app ./vendor/bin/phpunit

# Build frontend assets
docker compose exec app npm run build
```

## Tests

Run the PHPUnit unit and feature suite:

```bash
cd docker
docker compose exec app ./vendor/bin/phpunit
```

The backend suite covers authentication behavior, incident status authorization, backward status changes, escalation reason validation, resolved-incident locking, and mock AI summary generation.

Run the Playwright browser smoke tests against the Docker application:

```bash
cd docker
docker compose -f docker-compose.e2e.yml up --abort-on-container-exit --exit-code-from playwright playwright
```

The E2E Compose file includes the base and development Compose files automatically. The Playwright suite verifies protected-route redirects, seeded super-admin login, dashboard access, incident navigation, and asynchronous AI summary generation. The first run downloads the official Playwright Docker image.

## Timezone

The application, PHP runtime, Nginx, and MySQL are configured for Bangladesh time:

```text
Asia/Dhaka (UTC+06:00)
```
