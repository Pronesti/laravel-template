# AGENTS.md

Conventions and operating notes for anyone — human or agent — working in this codebase. `CLAUDE.md` is a symlink to this file; edit this one.

## Conventions

- `declare(strict_types=1);` at the top of every PHP file.
- Classes are `final` unless there is a specific reason to allow extension (there currently isn't one in `app/`).
- Models never expose their internal `id`. `App\Models\User::$hidden` includes `'id'`; the only public identifier is `external_id` (a UUIDv7 generated in PHP by `App\Models\Concerns\HasExternalId`, which also makes it the route-model-binding key via `getRouteKeyName()`). `UserResource` serializes it under the key `id` — the bigint primary key must never appear in a JSON response or a URL, and today it appears in neither: even the email-verification link is keyed on `external_id` (`GET /api/v1/email/verify/{user}/{hash}`, bound via `getRouteKeyName()`).
- All application routes live under `/api/v1` (`routes/api/v1.php`, registered as the `api` group in `bootstrap/app.php` with `apiPrefix: 'api/v1'`; Fortify's own routes share the same prefix via `config/fortify.php`'s `'prefix' => 'api/v1'`).
- Controllers are single-action invokables in `App\Http\Controllers\Api\V1`, e.g. `IssueTokenController`, `RevokeTokenController`, `MeController`.
- Every error response is `application/problem+json` (RFC 9457), built by `App\Http\Problem\ProblemFactory` and wired in `bootstrap/app.php`'s exception renderer. `type` defaults to `about:blank`; an exception can supply its own type/title/status by implementing `App\Http\Problem\ApiProblem`. Laravel's validation error bag is carried as the `errors` extension member on top of the standard `type`/`title`/`status`/`detail` fields.
- Larastan runs at `level: max` with **no baseline** (`phpstan.neon`). New code must satisfy it as-is — do not introduce a baseline to make an error disappear.
- `JsonResource` wrapping is **off** (`JsonResource::withoutWrapping()` in `AppServiceProvider::boot()`). Every resource returns its bare shape — no `{"data": ...}` envelope. This was a deliberate choice, not an oversight: it commits every future resource in this codebase to the same unwrapped shape, so don't wrap one resource and not another.

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
| `composer check` | `lint:test` → `stan` → `rector` → `test` — **run this before every push**; CI runs the same four stages |

There is deliberately no `composer dev`. Laravel's `php artisan dev` multiplexes its processes through `@laravel/multiplex` over `npx` on every platform (`concurrently` on Windows), which would put Node back into a template that otherwise needs none. Run `php artisan queue:listen` and `php artisan pail` in their own terminals when you want them.

## Auth model

Sanctum, token-only — there is no cookie/SPA mode configured out of the box (see below for how to switch).

- `POST /api/v1/tokens` (throttled to 6/min, tighter than the general 60/min API limiter) validates email/password and issues a personal access token. Invalid credentials — unknown email or wrong password — both return `422` with an identical body; `IssueTokenController` deliberately runs a dummy `Hash::check()` even when no user is found, so the response timing doesn't leak which case occurred either.
- `DELETE /api/v1/tokens/current` (behind `auth:sanctum`) revokes the token used to make the request.
- `GET /api/v1/me` (behind `auth:sanctum`) returns the authenticated user via `UserResource`.
- Fortify handles **registration and password reset** under the same `/api/v1` prefix (`config/fortify.php`). Email verification and the profile/password updates are template-owned routes in `routes/api/v1.php` — see below for why. Two-factor authentication and passkeys are **not** enabled — see below.
- `POST /api/v1/email/verification-notification` (`auth:sanctum`, 6/min) resends the verification mail; `GET /api/v1/email/verify/{user}/{hash}` (signed, **no** `auth:sanctum`) marks it verified. Both return `204`.
- `PUT /api/v1/user/profile-information` and `PUT /api/v1/user/password` (both `auth:sanctum`) return `204` and delegate to `App\Actions\Fortify\UpdateUserProfileInformation` / `UpdateUserPassword`.
- Token expiration (`config/sanctum.php`'s `'expiration'`) is `null` on purpose. There is no refresh-token flow in this template; an expiring token with no way to refresh it just breaks clients at an unpredictable time. If you add expiry, add a refresh mechanism in the same change.

## Why some Fortify features are disabled and re-registered by hand

`config/fortify.php` must keep `'guard' => 'web'`. Setting it to `sanctum` fatals: Fortify type-hints a `StatefulGuard` and calls `$guard->login()`, and Sanctum's guard is a `RequestGuard` with no `login()` method.

The consequence is that **every route Fortify registers behind authentication is guarded with `auth:web`**, which a bearer-token client — holding no session cookie — can never satisfy. So the features split by whether they need a logged-in user:

| Feature | Where it lives | Why |
|---|---|---|
| `registration`, `resetPasswords` | Fortify (`config/fortify.php`) | Unauthenticated; they work as shipped |
| `emailVerification`, `updateProfileInformation`, `updatePasswords` | **Disabled** on Fortify, hand-registered in `routes/api/v1.php` | Need `auth:sanctum` (or a signed URL), which Fortify cannot give them |

The hand-registered routes keep Fortify's paths and reuse the published `App\Actions\Fortify\*` actions through their contracts, so validation lives in exactly one place. `UpdateUserPassword` deliberately does **not** use Laravel's `current_password` rule: that rule resolves the user from a guard, which would couple the action to whichever guard is active. It verifies against the `$user` it was handed instead, so it behaves identically on the token route and on Fortify's session route if a project ever re-enables it.

`GET /api/v1/email/verify` (`verification.notice`) exists only so Laravel's `verified` middleware has somewhere to redirect non-JSON requests — without it, adding `->middleware('verified')` anywhere throws `RouteNotFoundException`. It always returns 403 problem+json. A token client never needs it: check `email_verified` on `GET /api/v1/me` instead.

`GET email/verify/{user}/{hash}` deliberately carries **no** `auth:sanctum`: the caller is a browser following a link out of an email and has no way to attach a bearer token. The URL's signature (`signed:relative`, matching how `App\Support\VerificationLink` signs it so the `FRONTEND_URL` host can be prefixed) plus the email hash is what proves ownership.

**Do not try to "simplify" this by pointing `fortify.guard` at `sanctum`.** The alternative that would also work — `Fortify::ignoreRoutes()` and hand-registering everything — is a much larger rewrite for no additional correctness.

## `POST /api/v1/login` and `/api/v1/logout` are not the token entry point

Fortify still registers these, and they are **session-based** (`auth:web`). `login` writes to the inert session described below; `logout` answers `401` to a bearer token because it is guarded by `auth:web`. The same is true of `POST /api/v1/user/confirm-password` and `GET /api/v1/user/confirmed-password-status`, which Fortify registers unconditionally.

The token entry point is **`POST /api/v1/tokens`**, and the way to invalidate a token is `DELETE /api/v1/tokens/current`. The Fortify session routes are left in place rather than removed because they cost nothing and a project switching to SPA cookie mode (recipe below) wants them — but nothing in this template's token flow goes through them, and no client should be pointed at them.

## Why Fortify routes carry `StartSession`

`config/fortify.php` registers Fortify's routes with `'middleware' => ['api', StartSession::class]`. This looks like a contradiction in a token-only API, but it's required: Fortify's registration flow calls `$guard->login()` on the stateful `web` guard internally (`Fortify::createUsersUsing`/the underlying `RegisteredUserController`), and that call needs a session to write to, or it throws. `StartSession` gives it one.

Nothing in this application authorizes a request off that session — every protected route uses `auth:sanctum`, not the session guard. The session Fortify starts is inert as far as the API is concerned. **Do not "fix" this by wiring the session guard into API routes** — that would reintroduce the CSRF/cookie surface this template deliberately avoids for a token-only API.

## Enabling two-factor authentication

Two-factor ships present but disabled. The `users` table already has `two_factor_secret`, `two_factor_recovery_codes`, and `two_factor_confirmed_at` columns, and `User` already uses `Laravel\Fortify\TwoFactorAuthenticatable` — so turning it on needs no migration.

To enable it, uncomment the feature in `config/fortify.php`:

```php
'features' => [
    Features::registration(),
    Features::resetPasswords(),
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
],
```

**Warning:** this template ships no 2FA token-challenge endpoint. Fortify's own two-factor flow is built for the stateful/session guard — it redirects a partially-authenticated session to a "confirm your code" screen. Token clients (`POST /api/v1/tokens`) have no equivalent: enabling this feature as-is will start requiring a second factor that no route in this API knows how to satisfy, locking every token client out. Before enabling it, write the challenge endpoint (issue a token, mark it as pending 2FA, add a route that accepts a code and upgrades it) and update `IssueTokenController` to be aware of the pending state.

## Filtering, sorting and includes (`spatie/laravel-query-builder`)

The package ships as a dependency with **no demo endpoint** — the template has exactly one resource route (`GET /api/v1/me`) and nothing to filter. Use it the first time an index endpoint needs query parameters, instead of hand-rolling `$request->query()` plumbing.

```php
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ListPostsController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $posts = QueryBuilder::for(Post::class)
            ->allowedFilters([
                'title',                                  // ?filter[title]=hello
                AllowedFilter::exact('status'),           // ?filter[status]=published
                AllowedFilter::scope('published_before'), // Post::scopePublishedBefore()
            ])
            ->allowedSorts(['created_at', 'title'])       // ?sort=-created_at
            ->allowedIncludes(['author'])                 // ?include=author
            ->paginate()
            ->appends(request()->query());

        return PostResource::collection($posts);
    }
}
```

Rules for this codebase:

- **The allowlists live in the controller**, next to the query they guard — not in the model, not in a config file. `allowedFilters`/`allowedSorts`/`allowedIncludes` are the authorization surface for what a client may query, so they belong where the route is read. An unlisted parameter raises `InvalidFilterQuery`, which renders as a `400` problem+json like everything else.
- **Never allow a filter or sort on `id`.** It is hidden from serialization for a reason; expose `external_id` if a client genuinely needs to filter by identity.
- Prefer `AllowedFilter::scope()` over `AllowedFilter::callback()` so the query logic stays testable on the model.
- Cover new allowlists with a feature test that asserts an *unlisted* parameter is rejected, not just that a listed one works.

## Switching Sanctum to SPA cookie mode

This template uses Sanctum's token-only mode. If you're building a first-party SPA instead of (or in addition to) issuing bearer tokens to third-party clients, switch to cookie mode:

1. Set `SANCTUM_STATEFUL_DOMAINS` (your frontend's host, e.g. `localhost:3000`) and `SESSION_DOMAIN` in `.env`.
2. Publish and edit `config/cors.php`, setting `'supports_credentials' => true` and restricting `allowed_origins` to your frontend.
3. Add the CSRF cookie route your SPA hits before its first state-changing request — Sanctum registers `GET /sanctum/csrf-cookie` for you already (`php artisan route:list` shows it); your frontend just needs to call it.
4. Move `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful` onto the `api` middleware group in `bootstrap/app.php`, ahead of `auth:sanctum`, so requests from a stateful domain authenticate via the session cookie instead of a bearer token.

Token issuance (`POST /api/v1/tokens`) and cookie auth can coexist — `EnsureFrontendRequestsAreStateful` only applies to requests from `SANCTUM_STATEFUL_DOMAINS`; everything else still needs a bearer token.

## Add per project (not in the template)

These are common, reasonable additions to a real Laravel API — they're left out of the template so a fresh project starts minimal, not because they're discouraged. **Deferred, not forbidden**: don't add any of these unprompted just because a task touches a related area: add them when the project actually needs them.

| Need | Add | Why it's not in the template |
|---|---|---|
| Error tracking | `composer require sentry/sentry-laravel` | Needs a DSN and a decision on sampling/PII scrubbing per project |
| Roles/permissions | `composer require spatie/laravel-permission` | Most APIs start with one role; adding this before there's a second is premature |
| Feature flags | `composer require laravel/pennant` | No flags exist yet — nothing to flag |
| Audit logging | `composer require spatie/laravel-activitylog` | Storage/retention policy is project-specific |
| APM/production monitoring | `composer require laravel/nightwatch` | Paid service; opt-in per project |
| Admin panel | Filament or Nova | Whether you want one at all is a product decision, not a template default |
| i18n scaffolding | Laravel's built-in localization + a translation workflow | Single-locale (`en`) is the common starting point |
| Multi-tenancy | e.g. `stancl/tenancy` | Architecture (single-DB vs. multi-DB) is a decision this template shouldn't make for you |
| Structured API input objects | `composer require spatie/laravel-data` | Form Requests are sufficient until DTOs earn their keep |
| Docker/Sail | `composer require laravel/sail --dev` | Deliberately omitted — see README requirements; keeping the template runtime-agnostic |
| Deploy pipeline | Project-specific (Forge, Envoyer, GitHub Actions deploy job, etc.) | Target infrastructure varies too much to template |

## Boost

This project uses [Laravel Boost](https://laravel.com/docs/ai) to give AI coding agents tool access and generated guidelines tailored to the installed package set. If you add or remove packages from the "add per project" list above (or anything else), re-run:

```bash
php artisan boost:install
```

so the guidelines Boost appends below stay in sync with what's actually installed. Boost's generated content lives below this line — don't hand-edit it; re-run the installer instead.

> **The generated block below was written for a full-stack Laravel app, and this is not one.**
> Ignore every instruction in it that assumes a frontend: there is no `package.json`, no
> `npm run build` / `npm run dev`, no Vite manifest, no Blade UI, and no `composer dev`
> script in this template. It also tells you to read `.ai/rules`, which this repo does not
> use. Where the block and the conventions above disagree, **the conventions above win.**
> Re-check that this caveat still sits directly above the block after re-running `boost:install`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
