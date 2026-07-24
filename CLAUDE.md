# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

`petSymphony` is a personal/learning CRM built on the [`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker) template. The author is learning Symfony. Auth, RBAC, password reset and the client core are implemented; notes are still a spec:

- **Auth, RBAC, forgot-password and clients are implemented** in a pragmatic DDD layout: entities `User` / `PasswordResetToken` / `Client` + repository interfaces in `src/Domain/` (Doctrine mapping points at `src/Domain`, **not** `src/Entity/`), use-case handlers in `src/Application/`, Doctrine/mailer/security adapters in `src/Infrastructure/` (domain ports are aliased to adapters in `config/services.yaml`, incl. `TransactionRunnerInterface`), HTTP controllers in `src/Controller/`.
- Session auth via `json_login` (`POST /api/login`, with `login_throttling` 5/min and a JSON failure handler); pages: `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/admin/users` (ROLE_ADMIN); admin API: `GET /api/admin/users`, `PATCH /api/admin/users/{id}/roles`. Everything else under `^/` requires `ROLE_USER` (unauthenticated HTML → redirect to `/login`, API → 401 JSON). Bootstrap the first admin: `bin/console app:user:promote <email>`. Expired reset tokens: `bin/console app:tokens:purge-expired` (cron in prod).
- **Client CRM core is live**: `GET/POST /api/clients`, `GET/PUT/DELETE /api/clients/{id}` (+`PATCH /{id}/restore`) per `docs/api/openapi.yaml`. `DELETE` is a **soft delete** (sets `archivedAt`; lists hide archived unless `?archived=1`). **Owner-scoped**: a plain ROLE_USER only sees clients they own (`ClientVoter` + owner filter in the repository); ROLE_MODERATOR/ROLE_ADMIN see all; foreign clients return 404 (not 403) to avoid id enumeration. The **notes** part of the contract is still a target.
- **API error envelope is uniform**: `{"message": string, "errors": object|null}` — controllers use `App\Controller\Api\ApiJson`, stray exceptions under `^/api` are shaped by `App\Infrastructure\Http\ApiExceptionListener` (in debug, non-HTTP exceptions keep the trace page). Registration/password endpoints are rate-limited via `config/packages/rate_limiter.yaml`; passwords require length ≥ 10 + `PasswordStrength` + `NotCompromisedPassword(skipOnError)`.
- **Mail is async via Messenger**: `SendEmailMessage` routes to a Doctrine transport (`messenger.yaml`), consumed by the dedicated `worker` compose service (`messenger:consume async --time-limit=3600`; disabled healthcheck; needs `DATABASE_URL` in compose `environment:` — the committed `.env` points at 127.0.0.1 for host-side CLI). Failed messages land in the `failed` transport (`messenger:failed:show`). Tests use `in-memory://`. Without a running worker, mails silently sit in `messenger_messages`.
- Feature specs and step-by-step decompositions live in `projectDoc/IDEAS/` (Russian, Obsidian vault — a **nested git repo**; `Backlog.md` indexes 50 tasks). Consult these before designing features — the user breaks work down there first, and statuses are updated as features land.
- CI (`.github/workflows/ci.yaml`) runs PHPUnit, migrations, Doctrine schema validation, PHPStan (level 8) and `composer audit`, plus a `frontend` job (ESLint, Stylelint, `tsc --noEmit`, Vitest, production build). Unit tests live in `tests/Unit/` with hand-written fakes in `tests/Fake/` (no mocking framework; a single PHPUnit stub is used only where an interface is too wide to fake by hand). React tests (Vitest + Testing Library + msw) live in `assets/tests/` — deliberately NOT co-located with components: `require.context` in `app.ts` would bundle and register any file under `assets/react/controllers/`.

## Stack

PHP 8.4 / Symfony 8.0 (MicroKernel) · FrankenPHP (Caddy worker mode, **not** php-fpm) · PostgreSQL 16 · Doctrine ORM 3.6 + Migrations 4 · Twig 3 · TypeScript / React 18 · Webpack Encore 5 · Symfony UX React 2.32 · Stimulus 3 · SCSS.

## Everyday commands

Everything runs inside the `php` container — do not invoke `composer`, `bin/console`, `npm`, or `php` on the host.

```bash
# Lifecycle
docker compose up --wait                              # start (also auto-runs migrations if any exist)
docker compose down --remove-orphans                  # stop
docker compose logs -f php node                       # follow PHP + asset rebuilds
docker compose exec php bash                          # shell into the app container

# Symfony / Doctrine
docker compose exec php bin/console debug:router
docker compose exec php bin/console cache:clear
docker compose exec php bin/console make:entity
docker compose exec php bin/console make:controller Foo
docker compose exec php bin/console make:migration
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php bin/console doctrine:schema:validate

# Frontend (the `node` service already runs `npm run watch` in dev — these are for one-offs)
docker compose run --rm node npm run dev              # one-off dev build
docker compose run --rm node npm run build            # production build (minify + version hashes)

# Tests
docker compose exec php bin/phpunit
docker compose exec php bin/phpunit --filter testMethodName tests/Unit/Path/SomeTest.php
docker compose run --rm node npm run test           # Vitest (react) — tests live in assets/tests/ (NOT co-located: require.context would bundle them)

# Static analysis / lint
docker compose exec php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker compose exec php composer audit
docker compose run --rm node npm run lint          # ESLint (flat config)
docker compose run --rm node npm run typecheck     # tsc --noEmit (strict)
docker compose run --rm node npm run stylelint
```

XDebug is on by default (`XDEBUG_MODE=debug` in `.env`); override per-session via `XDEBUG_MODE=off docker compose up --wait` to recover full-speed PHP. See `docs/ru-01-debug.md` for PhpStorm/VS Code wiring (path mapping `/app` ↔ project root, server name `symfony`, port 443/9003).

## Request → response pipeline (the part you have to read several files to see)

1. **Caddy/FrankenPHP** (`frankenphp/Caddyfile`) serves `public/index.php` in **worker mode** — one persistent PHP process per worker, not request-per-fork. The dev image runs with `--watch`, so PHP edits are picked up live. Static files under `public/` bypass PHP; anything else (and not `/.well-known/mercure*`) is rewritten to `index.php`.
2. **`public/index.php`** boots `App\Kernel` via `vendor/autoload_runtime.php` (no `Application::run` boilerplate — Symfony Runtime handles it).
3. **Routing** is attribute-driven: `config/routes.yaml` only does `resource: routing.controllers`, which scans `src/Controller/` for `#[Route]` attributes. There are no YAML route files.
4. **Controller** renders Twig (HTML page) or returns `JsonResponse` (API). The HTML page pulls compiled assets via `encore_entry_link_tags('app')` / `encore_entry_script_tags('app')`, which read `public/build/manifest.json` (versioned filenames).
5. **Twig → React** mounting uses Symfony UX React: `<div {{ react_component('Name', { propsHash }) }}></div>`. The `name` is the **filename** (without extension) under `assets/react/controllers/`, including any subdirectory (`admin/Dashboard.tsx` → `'admin/Dashboard'`). Registration is automatic — see step 6.
6. **Asset entry** is `assets/app.ts`. It imports `./stimulus_bootstrap.js` (Stimulus controllers under `assets/controllers/`, registered lazily from `controllers.json`) and calls `registerReactControllerComponents(require.context('./react/controllers', true, /\.([jt])sx?$/))` — every `.ts/.tsx/.js/.jsx` file in that tree is bundled and registered by filename. Adding a React component requires no manual registration.
7. **React → backend** goes through `assets/react/services/httpClient.ts` (single fetch wrapper: JSON headers, `X-Requested-With`, 15s timeout, parses the `{message, errors}` envelope into a typed `ApiError`, redirects to `/login?expired=1` on 401) plus per-domain services (`AuthApiService`, `PasswordResetApiService`, `RbacApiService`). Add new endpoints as methods on a domain service over `httpClient` — never raw `fetch` in components.

## Asset build pipeline & a non-obvious ordering rule

- Single Webpack Encore entry: `app` → `assets/app.ts` → emits `public/build/{app.js, app.css, runtime.js, manifest.json, entrypoints.json}` plus split vendor chunks. Sass/SCSS, TypeScript, React preset, and Stimulus bridge are enabled in `webpack.config.js`. `tsconfig.json` uses `jsx: "react-jsx"` (no `import React` needed).
- `package.json` declares `"@symfony/ux-react": "file:vendor/symfony/ux-react/assets"` — **Composer dependencies must be installed before `npm ci/install`**. The Dockerfile enforces this with a dedicated `php_vendor` stage that's copied into the `node_base` stage. If you ever rebuild assets outside Docker, run `composer install` first.
- `public/build/` is generated and gitignored; in dev the `node` service auto-rebuilds (`npm install && npm run watch`); in production `frankenphp_prod` copies `public/build/` from the `node_base` Docker stage during image build.

## Doctrine wiring

- `config/packages/doctrine.yaml`: entities live in `src/Entity/` (attribute mapping, prefix `App\Entity`, alias `App`), naming strategy is `underscore_number_aware`, and PostgreSQL uses `IDENTITY` for ID generation.
- `controller_resolver.auto_mapping: false` — you **cannot** auto-resolve an entity from a route placeholder by type-hinting it; inject the repository and call `find($id)` yourself, or enable mapping explicitly.
- `docker-entrypoint.sh` waits up to 60s for PostgreSQL, then runs `doctrine:migrations:migrate --no-interaction --all-or-nothing` if `migrations/*.php` exists. So a freshly started stack is always at the latest schema.
- The prod build also runs `composer dump-env prod` and `composer dump-autoload --classmap-authoritative`. Don't add post-install logic that breaks under `--no-dev`.

## Environments & secrets

- `.env` is committed and holds non-secret defaults (`APP_ENV=dev`, `XDEBUG_MODE=debug`, `DATABASE_URL`, `POSTGRES_*`). `.env.dev` adds a committed `APP_SECRET` for dev only.
- For local overrides use `.env.local` (gitignored). **Never** edit `.env` or `.env.dev` for secrets.
- In prod, `compose.prod.yaml` expects `APP_SECRET` and `CADDY_MERCURE_JWT_SECRET` from the host environment. Run prod with `-f compose.yaml -f compose.prod.yaml` in that order.

## Docker compose layout

- `compose.yaml`: base — `php` (FrankenPHP, ports 80/443 TCP + 443/UDP for HTTP/3) and `database` (Postgres 16).
- `compose.override.yaml` (auto-loaded in dev): bind-mounts the source, exposes `5432:5432`, adds the `node` watcher, and stands up a **stack of optional dev services**: `mailer` (Mailpit — UI on 8025, receives password-reset emails; `MAILER_DSN=smtp://mailer:1025` is set on the `php` service), `redis` (6379), `elasticsearch` + `kibana` (9200 / 5601), `swagger-ui` serving `docs/api/openapi.yaml` (8080), and a Prometheus / Grafana / node-exporter trio (9090 / 3000 / 9100, Grafana login `admin`/`admin`). Mailpit and **Redis are wired into Symfony** (Redis holds sessions via `handler_id`, `cache.app`, and the rate-limiter pool; `REDIS_URL` in `.env` points at 127.0.0.1 for host CLI, compose overrides it per service; the test env deliberately stays on filesystem cache + mock sessions so CI needs no Redis). **Prometheus/Grafana are wired too**: `GET /metrics` (`App\Controller\MetricsController`, IP-allowlisted for the docker network + `ROLE_ADMIN` fallback) exposes counters/histograms backed by Redis plus scrape-time gauges, and `docker/grafana/provisioning/` ships the dashboard + 5xx alert rule. Elasticsearch/Kibana remain not wired into code yet.
- `compose.prod.yaml`: switches build target to `frankenphp_prod` and injects required secrets.
- `Dockerfile` is multi-stage: `php_vendor` (composer install, no dev) → `node_base` (npm ci + `npm run build`) → `frankenphp_base` → `frankenphp_dev` (adds xdebug, watch mode) or `frankenphp_prod` (preload, no-dev autoload). Target via `--target` or via the compose service's `build.target`.

## External projects (Оркестр / submodules)

Other repos (e.g. Laravel apps) are linked as **git submodules** under `projects/<key>` and surfaced in the sidebar under **Оркестр → Проекты** (`/projects`, route `app_projects`, any `ROLE_USER`). Submodule repos stay untouched; petSymphony owns the integration via three per-`<key>` artifacts:

- **Registry** entry in `config/projects.yaml` (param `app.external_projects`, imported by `config/services.yaml`) → read by `App\Projects\ProjectRegistry` into `ExternalProject` DTOs → rendered server-side by `templates/projects/index.html.twig` (no React/API — static list). **The list is config, not parsed from `.gitmodules`**, which is `.dockerignore`d and absent in prod.
- **Caddy snippet** `frankenphp/projects/<key>.caddy` (`<key>.localhost { reverse_proxy <key>-php:80 }`). Imported **only in dev** via `CADDY_EXTRA_CONFIG: "import /app/frankenphp/projects/*.caddy"` (compose.override.yaml) expanded into the `{$CADDY_EXTRA_CONFIG}` placeholder in `frankenphp/Caddyfile`; empty/no-op in prod. Caddyfile changes need `docker compose restart php` (`--watch` only reloads PHP).
- **Compose override** `docker/projects/<key>.override.yaml` attaches the submodule's web service to the shared network `petsymphony_default` (name pinned via top-level `name: petsymphony` in compose.yaml) with a stable alias `<key>-php`.

Bring apps up after the main stack: `sh bin/projects up` (or per-app `docker compose -p <key> -f projects/<key>/compose.yaml -f docker/projects/<key>.override.yaml up -d`). `.dist` templates sit next to each artifact. Full guide: `docs/ru-проекты.md`.

## Project conventions

- **Git** (`projectDoc/GIT/Git (VSC).md`): branch names follow `name/type/num/nickname` where `type ∈ {feature, upgrade, optimize, refactor, bug}` (e.g. `give-access-to-x/feature/1/enrinko`). Commit subjects repeat the branch name, body lists what changed.
- **Feature workflow** (`projectDoc/IDEAS/Feature.md`): every new feature gets a folder under `projectDoc/IDEAS/<slug>/` with a top-level `<slug>.md` (description, author, complexity, status, type, ordered steps, suggested packages, estimate) and a `decomposition/NN-step.md` per atomic task. **Look here before designing** — `auth`, `rbac`, and `forgot-password` already have full decompositions ready to implement.
- **Russian docs**: user-facing project docs (`docs/ru-*.md`, `projectDoc/**`) are in Russian. Match that language when adding new docs in those locations; code-level comments and identifiers stay English unless the file you're editing already uses Russian.
- `config/reference.php` is auto-generated by Symfony Flex (psalm-style config shapes). Don't hand-edit; let recipes regenerate it.
- `projectDoc/` is an Obsidian vault and is excluded from Docker images via `.dockerignore` — keep it out of anything shipped.

## Documentation map

| Topic | File |
| --- | --- |
| Project overview / stack / .env reference | `docs/ru-обзор.md`, `projectDoc/Custom CRM.md` |
| First-time setup, Docker commands, prod deploy | `docs/ru-00-развертывание.md`, `docs/ru-production.md` |
| XDebug + PhpStorm/VS Code wiring | `docs/ru-01-debug.md` |
| Routing patterns (`#[Route]`, URL params, JSON endpoints) | `docs/ru-02-роутинг.md` |
| Twig + React + ApiService end-to-end example | `docs/ru-03-страницы.md` |
| Webpack Encore configuration walkthrough | `docs/ru-webpack.md` |
| Doctrine entity + migration recipes | `docs/ru-база-данных.md` |
| PostgreSQL backups + restore runbook | `docs/ru-бэкапы.md` |
| Planned CRM REST API contract | `docs/api/openapi.yaml` (also browsable at `http://localhost:8080` via the `swagger-ui` dev service) |
| Feature specs awaiting implementation | `projectDoc/IDEAS/{auth,rbac,forgot-password}/` |
| External projects (submodules + Оркестр menu) | `docs/ru-проекты.md` |