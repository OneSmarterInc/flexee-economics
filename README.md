# Halden Energy

A managerial economics simulation for MBA courses, part of Flexee. Teams of five run Halden
Energy, an integrated oil company, one quarter per course week. Each quarter they set the
company's numbers, talk to AI advisors, write a half-page memo, and see what happened.

This is version 2, rebuilt around the quarterly play-through approved on 9 October 2026.
Working rules for contributors are in `CLAUDE.md`.

## Requirements

- PHP 8.4 with pdo_sqlite (and pdo_mysql for MySQL)
- Composer 2
- Node.js 22 and npm

## Set up

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # with DB_CONNECTION=sqlite in .env
php artisan migrate
npm run build
```

## What's here so far

| Part                                    | Where                        | Status                                       |
| --------------------------------------- | ---------------------------- | -------------------------------------------- |
| Economics (Python reference)            | `packages/operating-model/`  | Quarters 1–4, 22 checks pass                 |
| Economics (PHP engine)                  | `app/Halden/OperatingModel/` | Matches the reference on every fixture value |
| Sign-in, settings, two-factor, passkeys | Laravel starter kit          | Working                                      |
| Student and faculty screens             | `resources/js/pages`         | Next (Phase 2)                               |

## Tests

```bash
php artisan test
python3 packages/operating-model/model/validate.py
```
