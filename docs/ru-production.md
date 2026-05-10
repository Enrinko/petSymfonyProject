# Развёртывание в продакшене

## Подготовка сервера

Для продакшена нужен Linux-сервер с установленным Docker Compose.
Вы можете использовать любой VPS-провайдер (DigitalOcean, Hetzner, Timeweb Cloud и др.).

Минимальные требования:
- **ОС:** Ubuntu 22.04 / Debian 12
- **RAM:** не менее 1 ГБ (2 ГБ рекомендуется для первой сборки)
- **Docker:** установлен и запущен

Установка Docker на Ubuntu:

```bash
curl -fsSL https://get.docker.com | sh
```

---

## Деплой

### 1. Скопировать проект на сервер

```bash
# Через git
git clone git@github.com:<username>/petSymphony.git
cd petSymphony
```

### 2. Настроить домен

Создайте DNS-запись типа `A`, которая указывает на IP вашего сервера:

```
your-domain.example.com.  IN  A  <ip-адрес-сервера>
```

### 3. Собрать продакшен-образ

```bash
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
```

> **Порядок `-f` важен.** `compose.prod.yaml` должен быть вторым.

### 4. Запустить приложение

```bash
SERVER_NAME=your-domain.example.com \
APP_SECRET=ВашСекретный32СимвольныйКлюч \
CADDY_MERCURE_JWT_SECRET=ВашМеркурКлюч \
POSTGRES_PASSWORD=НадёжныйПарольБД \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

Приложение будет доступно по `https://your-domain.example.com`.
TLS-сертификат выдаётся автоматически через Let's Encrypt.

---

## Генерация секретных ключей

```bash
# APP_SECRET — случайная строка 32+ символов
openssl rand -hex 32

# CADDY_MERCURE_JWT_SECRET
openssl rand -base64 32
```

---

## Запуск только по HTTP (без HTTPS)

```bash
SERVER_NAME=:80 \
APP_SECRET=ВашКлюч \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

---

## Важные отличия prod от dev

| Аспект | Dev | Prod |
|-------|-----|------|
| XDebug | Включён (если задан XDEBUG_MODE) | Отключён |
| Кэш | Сбрасывается автоматически | Собирается при сборке |
| Ошибки | Полный стектрейс | Только статус-код |
| Ресурсы | Webpack dev-сборка | Оптимизированная сборка |
| Volumes | Исходники монтируются | Копируются в образ |

---

## Обновление приложения

```bash
# Получить новый код
git pull

# Пересобрать образ
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache

# Перезапустить
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

---

## Переменные окружения в продакшене

Создайте `.env.prod.local` на сервере (файл не коммитится):

```dotenv
APP_SECRET=реальный_секрет
POSTGRES_PASSWORD=реальный_пароль
CADDY_MERCURE_JWT_SECRET=реальный_mercure_ключ
DATABASE_URL="postgresql://app:реальный_пароль@database:5432/app?serverVersion=16&charset=utf8"
```

Подключение в `compose.prod.yaml`:

```yaml
services:
  php:
    env_file:
      - .env.prod.local
```

---

## Логи в продакшене

```bash
# Логи приложения
docker compose -f compose.yaml -f compose.prod.yaml logs php

# Следить за логами в реальном времени
docker compose -f compose.yaml -f compose.prod.yaml logs --follow php
```