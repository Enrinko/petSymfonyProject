# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

`petSymphony` is a personal/learning CRM built on the [`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker) template. The author is learning Symfony — much of the planned domain (clients, auth, RBAC, password reset) is **specified but not yet implemented**:

- `src/Entity/`, `src/Repository/`, `migrations/` are empty (only `.gitignore` stubs).
- The only controller is `src/Controller/HomeController.php` with `/` (renders `base.html.twig`) and `/deposit` (returns an empty `JsonResponse` — placeholder consumed by `DepositForm.tsx`).
- The CRM API contract in `docs/api/openapi.yaml` (clients + notes) is a **target**, not implemented.
- Feature specs and step-by-step decompositions live in `projectDoc/IDEAS/{auth,rbac,forgot-password}/` (Russian, Obsidian vault). Consult these before designing features in those areas — the user has already broken the work down.
- `.github/workflows/ci.yaml` has PHPUnit / Doctrine validation / migration steps gated behind `if: false` — flip them on as the corresponding pieces land. No test framework is installed yet.

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

# Tests (not yet installed — see CI gating)
docker compose exec php bin/phpunit                   # once symfony/phpunit-bridge is added
docker compose exec php bin/phpunit tests/Path/To/SomeTest.php::testMethod
```

XDebug is on by default (`XDEBUG_MODE=debug` in `.env`); override per-session via `XDEBUG_MODE=off docker compose up --wait` to recover full-speed PHP. See `docs/ru-01-debug.md` for PhpStorm/VS Code wiring (path mapping `/app` ↔ project root, server name `symfony`, port 443/9003).

## Request → response pipeline (the part you have to read several files to see)

1. **Caddy/FrankenPHP** (`frankenphp/Caddyfile`) serves `public/index.php` in **worker mode** — one persistent PHP process per worker, not request-per-fork. The dev image runs with `--watch`, so PHP edits are picked up live. Static files under `public/` bypass PHP; anything else (and not `/.well-known/mercure*`) is rewritten to `index.php`.
2. **`public/index.php`** boots `App\Kernel` via `vendor/autoload_runtime.php` (no `Application::run` boilerplate — Symfony Runtime handles it).
3. **Routing** is attribute-driven: `config/routes.yaml` only does `resource: routing.controllers`, which scans `src/Controller/` for `#[Route]` attributes. There are no YAML route files.
4. **Controller** renders Twig (HTML page) or returns `JsonResponse` (API). The HTML page pulls compiled assets via `encore_entry_link_tags('app')` / `encore_entry_script_tags('app')`, which read `public/build/manifest.json` (versioned filenames).
5. **Twig → React** mounting uses Symfony UX React: `<div {{ react_component('Name', { propsHash }) }}></div>`. The `name` is the **filename** (without extension) under `assets/react/controllers/`, including any subdirectory (`admin/Dashboard.tsx` → `'admin/Dashboard'`). Registration is automatic — see step 6.
6. **Asset entry** is `assets/app.ts`. It imports `./stimulus_bootstrap.js` (Stimulus controllers under `assets/controllers/`, registered lazily from `controllers.json`) and calls `registerReactControllerComponents(require.context('./react/controllers', true, /\.([jt])sx?$/))` — every `.ts/.tsx/.js/.jsx` file in that tree is bundled and registered by filename. Adding a React component requires no manual registration.
7. **React → backend** goes through `assets/react/services/ApiService.ts` (plain `fetch`, same-origin, JSON). Add new methods here rather than scattering `fetch` calls in components.

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
- `compose.override.yaml` (auto-loaded in dev): bind-mounts the source, exposes `5432:5432`, adds the `node` watcher, and stands up a **stack of optional dev services**: `redis` (6379), `elasticsearch` + `kibana` (9200 / 5601), `swagger-ui` serving `docs/api/openapi.yaml` (8080), and a Prometheus / Grafana / node-exporter trio (9090 / 3000 / 9100, Grafana login `admin`/`admin`). None of these are currently wired into Symfony code — they're scaffolding for upcoming features.
- `compose.prod.yaml`: switches build target to `frankenphp_prod` and injects required secrets.
- `Dockerfile` is multi-stage: `php_vendor` (composer install, no dev) → `node_base` (npm ci + `npm run build`) → `frankenphp_base` → `frankenphp_dev` (adds xdebug, watch mode) or `frankenphp_prod` (preload, no-dev autoload). Target via `--target` or via the compose service's `build.target`.

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
| Planned CRM REST API contract | `docs/api/openapi.yaml` (also browsable at `http://localhost:8080` via the `swagger-ui` dev service) |
| Feature specs awaiting implementation | `projectDoc/IDEAS/{auth,rbac,forgot-password}/` |