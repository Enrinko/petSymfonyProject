# Security Baseline Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Закрыть четыре оставшихся ⚠️ пункта из security baseline: non-root контейнер, healthcheck-эндпоинты, K8s probes и перегруженный CI stack.

**Architecture:** Четыре независимых изменения — (1) PHP контроллер, (2) K8s патч, (3) Dockerfile, (4) compose.ci.yaml + CI workflow. Порядок исполнения: 1 → 2 → 3 → 4 (2 логически зависит от 1, остальные независимы).

**Tech Stack:** PHP 8.4 / Symfony 8.0 / FrankenPHP (Debian-based) / Kustomize / GitHub Actions / Docker Compose v2

---

## Карта файлов

| Файл | Действие | Ответственность |
|---|---|---|
| `src/Controller/HealthController.php` | Создать | `/healthz` (liveness) и `/readyz` (readiness + DB-пинг) |
| `kustomize/base/deployment.yaml` | Изменить | Переключить probes с `/` на `/healthz`/`/readyz` |
| `Dockerfile` | Изменить | Добавить non-root user в `frankenphp_prod`, `USER app` |
| `compose.ci.yaml` | Создать | Минимальный stack для CI (php + database, без Elasticsearch и др.) |
| `.github/workflows/ci.yaml` | Изменить | Использовать `compose.ci.yaml` вместо `compose.override.yaml` |

---

## Task 1: HealthController — `/healthz` и `/readyz`

**Files:**
- Create: `src/Controller/HealthController.php`

Контроллер не расширяет `AbstractController` — ни DI, ни render не нужен, только JSON.
`/readyz` инжектирует `Doctrine\DBAL\Connection` и делает `SELECT 1`; при исключении возвращает 503.

- [ ] **Step 1: Создать контроллер**

Создать файл `src/Controller/HealthController.php` с содержимым:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/healthz', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/readyz', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(
                ['status' => 'error', 'db' => 'unreachable'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
```

- [ ] **Step 2: Проверить маршруты**

```bash
docker compose exec php bin/console debug:router | grep -E "healthz|readyz"
```

Ожидаемый вывод (два маршрута):
```
 health_live    ANY      ANY    ANY  /healthz
 health_ready   ANY      ANY    ANY  /readyz
```

- [ ] **Step 3: Проверить эндпоинты через curl**

```bash
docker compose exec php curl -sf http://localhost/healthz
# Expected: {"status":"ok"}

docker compose exec php curl -sf http://localhost/readyz
# Expected: {"status":"ok"}  (если БД доступна)
```

- [ ] **Step 4: Commit**

```bash
git add src/Controller/HealthController.php
git commit -m "feat: add /healthz and /readyz health endpoints"
```

---

## Task 2: Обновить K8s probes с `/` на `/healthz` / `/readyz`

**Files:**
- Modify: `kustomize/base/deployment.yaml`

- [ ] **Step 1: Обновить liveness и readiness probe**

В файле `kustomize/base/deployment.yaml` заменить блок `livenessProbe` / `readinessProbe`:

```yaml
# Было:
          livenessProbe:
            httpGet:
              path: /
              port: http
            initialDelaySeconds: 15
            periodSeconds: 10
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /
              port: http
            initialDelaySeconds: 5
            periodSeconds: 5
            failureThreshold: 3

# Стало:
          livenessProbe:
            httpGet:
              path: /healthz
              port: http
            initialDelaySeconds: 10
            periodSeconds: 10
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /readyz
              port: http
            initialDelaySeconds: 5
            periodSeconds: 5
            failureThreshold: 3
```

- [ ] **Step 2: Проверить рендер Kustomize**

```bash
kubectl kustomize kustomize/overlays/dev | grep -A 6 "livenessProbe"
```

Ожидаемый вывод содержит `path: /healthz` и `path: /readyz`.

- [ ] **Step 3: Commit**

```bash
git add kustomize/base/deployment.yaml
git commit -m "fix: update k8s probes to /healthz and /readyz"
```

---

## Task 3: Dockerfile — non-root user в `frankenphp_prod`

**Files:**
- Modify: `Dockerfile`

FrankenPHP образ Debian-based, поэтому синтаксис `groupadd`/`useradd` (не Alpine).
В K8s контейнер слушает `:8080` (ConfigMap `SERVER_NAME=:8080`), поэтому `NET_BIND_SERVICE` не нужен.
`chown -R app:app var/` нужен внутри того же `RUN`, что создаёт директории, чтобы минимизировать слои.

- [ ] **Step 1: Добавить создание пользователя в `frankenphp_prod`**

В `Dockerfile` после строки `COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/` и ПЕРЕД первым `COPY --link composer.* symfony.* ./` добавить:

```dockerfile
RUN groupadd --system --gid 10001 app \
 && useradd  --system --no-create-home --uid 10001 --gid 10001 --shell /usr/sbin/nologin app
```

- [ ] **Step 2: Добавить chown и USER в конец `frankenphp_prod`**

Найти последний блок `RUN set -eux` в стадии `frankenphp_prod` (около строки 118) и добавить `chown` внутрь:

```dockerfile
# Было:
RUN set -eux; \
	mkdir -p var/cache var/log var/share; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync;

# Стало:
RUN set -eux; \
	mkdir -p var/cache var/log var/share; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; \
	chown -R app:app var/; \
	sync;

USER app
```

- [ ] **Step 3: Проверить что образ собирается**

```bash
docker compose build php 2>&1 | tail -20
```

Ожидается: `=> exporting to image` без ошибок.

- [ ] **Step 4: Проверить UID внутри prod образа**

```bash
docker build --target frankenphp_prod -t pet-symphony-prod-test . \
  && docker run --rm pet-symphony-prod-test id
```

Ожидается: `uid=10001(app) gid=10001(app)`.

- [ ] **Step 5: Убрать `runAsNonRoot` комментарий из deployment.yaml**

В `kustomize/base/deployment.yaml` удалить три строки комментария над `runAsNonRoot`:

```yaml
# Было:
      securityContext:
        # Requires adding USER 10001 to the frankenphp_prod Dockerfile stage.
        # Until then, pods will not schedule — this is intentional.
        # See SECURITY.md for the remediation command.
        runAsNonRoot: true
        runAsUser: 10001

# Стало:
      securityContext:
        runAsNonRoot: true
        runAsUser: 10001
```

- [ ] **Step 6: Обновить SECURITY.md — закрыть пункт 1**

В `SECURITY.md` и `SECURITY.ru.md` в таблице Enforced baseline изменить статус строки 1:

```markdown
| 1 | Container non-root (UID 10001) | `Dockerfile` → K8s `runAsNonRoot: true` | ✅ |
```

И в разделе "Overrides and known gaps" / "Overrides и известные пробелы" удалить весь подраздел `### 1. Container runs as root`.

- [ ] **Step 7: Commit**

```bash
git add Dockerfile kustomize/base/deployment.yaml SECURITY.md SECURITY.ru.md
git commit -m "fix: add non-root user (UID 10001) to frankenphp_prod stage"
```

---

## Task 4: compose.ci.yaml + CI workflow

**Files:**
- Create: `compose.ci.yaml`
- Modify: `.github/workflows/ci.yaml`

**Проблема:** `docker compose up --wait` автоматически загружает `compose.override.yaml`, который поднимает Elasticsearch (512 MB heap + ~500 MB overhead), Kibana, Prometheus, Grafana, node-exporter, swagger-ui. На GitHub Actions runner (7 GB RAM) это граничное состояние.

**Решение:** `compose.ci.yaml` — аналог `compose.override.yaml` только с `php` и `database`. CI job устанавливает `COMPOSE_FILE=compose.yaml:compose.ci.yaml`, блокируя автозагрузку override.

- [ ] **Step 1: Создать `compose.ci.yaml`**

```yaml
---
# CI-only overlay: only php + database (no Elasticsearch, Redis, Grafana, etc.)
# Used via COMPOSE_FILE=compose.yaml:compose.ci.yaml in GitHub Actions.
services:
  php:
    build:
      context: .
      target: frankenphp_dev
    volumes:
      - ./:/app
      - ./frankenphp/Caddyfile:/etc/frankenphp/Caddyfile:ro
      - ./frankenphp/conf.d/20-app.dev.ini:/usr/local/etc/php/app.conf.d/20-app.dev.ini:ro
    environment:
      FRANKENPHP_WORKER_CONFIG: watch
      MERCURE_EXTRA_DIRECTIVES: demo
      XDEBUG_MODE: "off"
      APP_ENV: "${APP_ENV:-dev}"
    extra_hosts:
      - host.docker.internal:host-gateway
    tty: true
```

- [ ] **Step 2: Обновить `.github/workflows/ci.yaml`**

В job `tests` добавить переменную окружения на уровне job и изменить два шага:

**Добавить `env:` после `runs-on:`:**
```yaml
  tests:
    name: Tests
    runs-on: ubuntu-latest
    env:
      COMPOSE_FILE: "compose.yaml:compose.ci.yaml"
```

**Изменить `files:` в bake-action:**
```yaml
      - name: Build Docker images
        uses: docker/bake-action@v6
        with:
          pull: true
          load: true
          source: .
          files: |
            compose.yaml
            compose.ci.yaml
          set: |
            *.cache-from=type=gha,scope=${{github.ref}}
            *.cache-from=type=gha,scope=refs/heads/main
            *.cache-to=type=gha,scope=${{github.ref}},mode=max
```

Остальные шаги (`Start services`, `docker compose exec`) не меняются — переменная `COMPOSE_FILE` подхватывается автоматически.

- [ ] **Step 3: Проверить что compose.ci.yaml валиден**

```bash
docker compose -f compose.yaml -f compose.ci.yaml config --quiet
```

Ожидается: выход 0, без ошибок (предупреждения об orphans игнорируем).

- [ ] **Step 4: Проверить что только php + database стартует**

```bash
docker compose -f compose.yaml -f compose.ci.yaml up --wait --no-build 2>&1 | grep "Started"
```

Ожидается только `php` и `database` в выводе.

- [ ] **Step 5: Добавить `compose.ci.yaml` в `.dockerignore` (если его нет)**

```bash
grep -q "compose.ci.yaml" .dockerignore || echo "compose.ci.yaml" >> .dockerignore
```

- [ ] **Step 6: Commit**

```bash
git add compose.ci.yaml .github/workflows/ci.yaml .dockerignore
git commit -m "fix: use lightweight compose.ci.yaml in CI to avoid OOM (removes ES, Grafana stack)"
```

---

## Self-Review

**Покрытие spec:**
- ✅ `/healthz` + `/readyz` → Task 1
- ✅ K8s probes обновлены → Task 2
- ✅ non-root Dockerfile → Task 3
- ✅ compose.ci.yaml + CI update → Task 4
- ✅ SECURITY.md обновлён → Task 3 Step 6

**Placeholders:** нет — весь код конкретный.

**Типы / имена:** `HealthController`, `Connection` из `Doctrine\DBAL`, `live()`/`ready()` — согласованны в Task 1 и Task 2.

**Возможные проблемы:**
- `composer run-script --no-dev post-install-cmd` в `frankenphp_prod` может завершаться с ошибкой если скрипт рассчитывает на запись в `var/` до `chown`. Порядок в Task 3 Step 2 сохраняет `chown` ПОСЛЕ `run-script` — это правильно.
- `kubectl kustomize` в Step 2 требует установленного kubectl. Если не установлен, пропустить и проверить через `cat kustomize/base/deployment.yaml`.
