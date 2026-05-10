# Параметры сборки Docker

## Переменные окружения Docker

Вы можете настраивать процесс сборки и запуска через переменные окружения.

> **Примечание:** Symfony-специфичные переменные (`SYMFONY_VERSION`, `STABILITY`) применяются только если в директории проекта **нет** файла `composer.json`. Поскольку в petSymphony `composer.json` уже существует, эти переменные **не влияют** на сборку.

---

## Порты сервера

По умолчанию приложение слушает стандартные порты. Если они заняты — переопределите:

**Linux / macOS:**

```bash
HTTP_PORT=8000 HTTPS_PORT=4443 HTTP3_PORT=4443 docker compose up --wait
```

**Windows (PowerShell):**

```powershell
$env:HTTP_PORT="8000"; $env:HTTPS_PORT="4443"; docker compose up --wait
```

После этого приложение доступно по `https://localhost:4443`.

> **Важно:** Let's Encrypt выдаёт сертификаты только на стандартные порты 80 и 443. При использовании нестандартных портов на продакшене — настройте другой CA или используйте кастомные сертификаты.

---

## Переменные конфигурации Caddy

Все переменные ниже можно задать в файле `.env` для постоянного применения.

| Переменная | Описание | Значение по умолчанию |
|-----------|---------|----------------------|
| `SERVER_NAME` | Имя / адрес сервера | `localhost` |
| `CADDY_GLOBAL_OPTIONS` | Глобальные опции Caddy (блок options) | — |
| `CADDY_EXTRA_CONFIG` | Сниппеты и именованные маршруты Caddy | — |
| `CADDY_SERVER_EXTRA_DIRECTIVES` | Дополнительные директивы Caddyfile | — |
| `CADDY_SERVER_LOG_OPTIONS` | Настройки логирования сервера | — |
| `FRANKENPHP_CONFIG` | Глобальные директивы FrankenPHP | — |
| `FRANKENPHP_WORKER_CONFIG` | Директивы worker FrankenPHP | `watch` (в dev) |
| `MERCURE_PUBLISHER_JWT_KEY` | JWT-ключ для публикаторов Mercure | — |
| `MERCURE_PUBLISHER_JWT_ALG` | JWT-алгоритм для публикаторов | `HS256` |
| `MERCURE_SUBSCRIBER_JWT_KEY` | JWT-ключ для подписчиков Mercure | — |
| `MERCURE_SUBSCRIBER_JWT_ALG` | JWT-алгоритм для подписчиков | `HS256` |
| `MERCURE_EXTRA_DIRECTIVES` | Дополнительные директивы Mercure | `demo` (в dev) |

### Изменить имя сервера

```bash
SERVER_NAME="app.localhost" docker compose up --wait
```

Или в `.env`:

```dotenv
SERVER_NAME=app.localhost
```

---

## Выбор версии Symfony (только при создании нового проекта)

Применяется **только** если `composer.json` отсутствует:

**Linux:**

```bash
SYMFONY_VERSION=6.4.* docker compose up --wait
```

**Windows:**

```cmd
set SYMFONY_VERSION=6.4.*&& docker compose up --wait&set SYMFONY_VERSION=
```

## Нестабильная версия Symfony

```bash
STABILITY=dev docker compose up --wait
```