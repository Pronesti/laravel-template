# Using this template

This file is for the person creating a new project from the template. `bin/init` deletes it, so it never reaches a generated project.

## Quickstart

1. Click **Use this template** on GitHub and create your new repository from it.
2. Clone your new repository and run:
   ```bash
   bin/init acme/billing-api "Billing API"
   ```
   This rewrites `composer.json`'s name/description, sets `APP_NAME` in `.env`/`.env.example`, retitles the README, removes this file and `docs/superpowers/` (the planning history for *this* template, which has no business in your project), installs dependencies, generates an app key, and deletes itself. It stages the result with `git add -A` but does not commit — review the diff and commit when you're happy.
3. Create the Postgres database named in your `.env` (default `DB_DATABASE=laravel`). `bin/init` does not do this for you.
4. Run:
   ```bash
   composer setup
   ```
   This installs dependencies (again, harmlessly, if step 2 already ran), copies `.env.example` to `.env` if missing, generates an app key, and runs migrations.

`bin/init` does **not** reset git history: GitHub's "Use this template" already gives you a clean single-commit history, so wiping `.git` would only destroy information.

## First five minutes on the new repo

GitHub does not copy repository settings from a template — only files. Do these once, by hand, right after creating your repo:

- Enable branch protection on `main` (at minimum: require a passing status check before merge).
- Set **Actions → General → Workflow permissions** to read-only. The CI workflow only needs to read the repo and run checks; it should never default to write access.
- Enable **delete branch on merge** (Settings → General).
- Confirm Dependabot is active (Settings → Code security → Dependabot). The config (`.github/dependabot.yml`) ships in the repo, but Dependabot alerts/updates must be enabled at the repository or organization level to actually run.

## Sanity check before you trust this template

CI never runs `bin/init` and never touches a real Postgres database — the test suite runs against in-memory SQLite, and nothing in `.github/workflows/` exercises the bootstrap script. That gap is accepted, not hidden: SQLite and Postgres agree on almost everything this schema uses, but the partial unique index (`CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL`) is exactly the kind of statement that's most likely to behave differently on the database you'll actually run in production.

So, once, after generating a real project from this template:

1. Point `.env` at a real PostgreSQL instance and run `php artisan migrate`. Confirm it succeeds.
2. `POST /api/v1/tokens` with a registered user's credentials and confirm you get back a token. Then `GET /api/v1/me` with that token and confirm the response looks right (an `id` field holding a UUID, no `password` field).

Do this before you build anything on top of the template — it's cheap insurance against a migration that only fails on the database that matters.
