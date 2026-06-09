# FGL Incident & Operations Tracking

## Summary

An operations intelligence application for tracking incidents, operational activity, ownership, and SLA status. The current version includes Dockerized Laravel and Vue, session authentication, user roles, password changes, user creation, and an initial dashboard.

## Technologies

- PHP 8.5
- Laravel 13
- Vue 3
- Vite
- Tailwind CSS
- Nginx
- MySQL 8.4
- Docker Compose

## Requirements

- Docker Desktop or Docker Engine
- Docker Compose
- Available ports: `8000`, `5173`, and `3307`

## Prerequisites

No host installation of PHP, Composer, Node.js, or MySQL is required.

## Initial Setup

```bash
cd docker

cp .env.example .env
cp envs/app.env.example envs/app.env
cp envs/mysql.env.example envs/mysql.env
```

Generate an application key:

```bash
docker compose run --rm app php artisan key:generate --show
```

Add the generated value to `docker/envs/app.env`:

```env
APP_KEY=base64:generated-value
```

Start the containers and initialize the database:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

## How To Run

Run commands from the `docker/` directory.

```bash
docker compose up -d
```

Open:

```text
http://localhost:8000
```

Stop the application:

```bash
docker compose down
```

## Project Structure

```text
codes/              Laravel and Vue application
docker/             Dockerfiles, Compose files, configs, and env files
docs/ROADMAP.md     Implementation roadmap
```

## Demo Login Credentials

```text
Email: super.admin@fgl.com
Password: fgl@admin
```

Other seeded users use the temporary password `password`:

```text
admin@fgl.com
support@fgl.com
viewer@fgl.com
```

## User Role Types

- `super_admin`
- `admin`
- `support_engineer`
- `viewer`

## Authentication Behavior

- Authentication uses Laravel's web guard and database sessions.
- Accounts must have `status=active`.
- Accounts with `must_change_password=true` are redirected to change their temporary password.
- Only `super_admin` and `admin` users can create users.
- Rejected users are logged out and shown a contact-support message.
- Successful login regenerates the session ID and redirects to `/dashboard`.
- Session lifetime is 120 minutes of inactivity.
