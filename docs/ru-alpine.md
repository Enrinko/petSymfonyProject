# Alpine Linux вместо Debian

## В чём разница

| Аспект | Debian (дефолт) | Alpine |
|-------|-----------------|--------|
| Размер образа | Больше | Меньше (~50%) |
| Производительность | Выше | Ниже (известные проблемы с musl libc) |
| Стабильность | Рекомендуется | Известные проблемы |
| Поддержка пакетов | `apt-get` | `apk` |

**Рекомендуется оставить Debian** — это официальная рекомендация проекта Symfony Docker.

---

## Как переключиться на Alpine

Внесите изменения в `Dockerfile`:

```dockerfile
# Было:
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
    file \
    git \
    && rm -rf /var/lib/apt/lists/*

# Стало:
FROM dunglas/frankenphp:1-php8.4-alpine AS frankenphp_upstream

# hadolint ignore=DL3018
RUN apk add --no-cache \
    file \
    git
```

Затем пересоберите образ:

```bash
docker compose build --pull --no-cache
docker compose up --wait
```