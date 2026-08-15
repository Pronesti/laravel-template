# Laravel API Template

> Creating a new project from this template? Start with [TEMPLATE.md](TEMPLATE.md). `bin/init` removes both that file and this line.

## What this is

A GitHub template for token-authenticated Laravel JSON APIs. It ships an API-only Laravel 13 skeleton — Sanctum for tokens, Fortify for registration and password reset, token-guarded email verification and profile/password updates, RFC 9457 problem+json errors, an opaque public identifier scheme, and a `composer check` gate wired into CI — so a new project starts from a working, reviewed baseline instead of a blank `laravel new`.

## Requirements

- PHP 8.4+
- Composer
- A PostgreSQL instance you provide

The template ships no Sail, no Docker Compose, and no Vite/npm toolchain — it is API-only. It does not create a database for you: point `DB_*` in `.env` at a Postgres instance you already have running (local install, Docker container you manage yourself, or a hosted instance) before running migrations.

## Setup

Point `DB_*` in `.env` at a PostgreSQL instance you have running, create the database named there, then:

```bash
composer setup
```

That installs dependencies, copies `.env.example` to `.env` if it is missing, generates an application key, and runs migrations.

## Commands

| Command | What it does |
|---|---|
| `composer setup` | Install deps, create `.env`, generate app key, run migrations |
| `composer serve` | Run the development server on `http://127.0.0.1:8000` |
| `composer test` | Clear cached config, run the full Pest suite in parallel |
| `composer test:dirty` | Run only tests touching files changed since the last commit (local use only — not run in CI) |
| `composer lint` | Fix code style with Pint |
| `composer stan` | Static analysis with Larastan (max level, no baseline) |
| `composer rector` | Check for automated refactors, dry-run |
| `composer check` | `lint:test` → `stan` → `rector` → `test`, in that order — the pre-push gate |

## What's included / what isn't

Included: Sanctum token auth, Fortify account management, problem+json errors, an external-ID identity scheme, Larastan/Pint/Rector/Pest wired into `composer check`, Scramble API docs (local only), Telescope (local only), CI (lint/stan/rector + tests on PHP 8.4 and 8.5) and a weekly unlocked-dependency canary, Dependabot.

Not included, and deliberately left out for you to add per project: error tracking, roles/permissions, feature flags, activity logging, an admin panel, i18n, multi-tenancy, Docker/Sail, and a deploy pipeline. See `AGENTS.md` → "Add per project" for what each of these is, why it isn't here by default, and the one-line `composer require` to add it when you need it.

For conventions, the auth model, and everything an agent (or you, in six months) needs to work in this codebase, read `AGENTS.md`.
