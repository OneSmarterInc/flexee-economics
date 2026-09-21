# Halden Energy

Laravel foundation for the Halden Energy managerial economics simulation.

## Prerequisites

- PHP 8.3 or newer
- Composer
- Node.js 18 or newer
- npm
- PostgreSQL for local development

The current scaffold was verified with Laravel 13, PHP 8.4, Node 18, and npm 10.

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for your local PostgreSQL database. Do not commit `.env`.

## Database

```bash
php artisan migrate
php artisan db:seed
```

The development seeder creates a fictional demo tenant:

- Institution: Halden University Demo
- Course: Managerial Economics
- Sections: Section A and Section B
- Users: demo administrator, faculty, and students
- Teams: Team Alpha and Team Bravo

Development-only credentials:

- `admin@example.test` / `password`
- `faculty@example.test` / `password`
- `student11@example.test` / `password`

These credentials are for local development only.

## Run Locally

Backend:

```bash
php artisan serve
```

Frontend:

```bash
npm run dev
```

## Test And Build

```bash
php artisan test
composer run lint:check
composer run types:check
npm run check
npm run types:check
npm run build
```

## Batch 1 Scope

Batch 1 establishes the Laravel application foundation: authentication, explicit tenant ownership, academic entities, team/seat structure, policies, development seed data, and isolation tests.

Week 4 economics, simulation lifecycle, scoring, KPIs, rankings, Python artifact generation, and LLM integration are intentionally deferred.
