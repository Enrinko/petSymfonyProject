# TLS-сертификаты (HTTPS)

## Проблема с самоподписанным сертификатом

При первом запуске браузер показывает предупреждение о небезопасном соединении — потому что локальный CA (центр сертификации), которым Caddy подписывает сертификат, не входит в список доверенных на вашей машине.

Есть два решения: **добавить CA в доверенные** или **использовать кастомный сертификат**.

---

## Решение 1 — Добавить локальный CA в доверенные

### Windows

```cmd
docker compose cp php:/data/caddy/pki/authorities/local/root.crt %TEMP%/root.crt && certutil -addstore -f "ROOT" %TEMP%/root.crt
```

### macOS

```bash
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /tmp/root.crt \
  && sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/root.crt
```

### Linux

```bash
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt \
  /usr/local/share/ca-certificates/root.crt \
  && sudo update-ca-certificates
```

После этой операции перезапустите браузер — предупреждение исчезнет.

---

## Решение 2 — Кастомный сертификат через mkcert

[mkcert](https://github.com/FiloSottile/mkcert) — инструмент для создания локально доверенных сертификатов.

### Шаг 1 — Установить mkcert

**Windows (через Chocolatey):**

```powershell
choco install mkcert
mkcert -install
```

**macOS:**

```bash
brew install mkcert
mkcert -install
```

### Шаг 2 — Создать директорию для сертификатов

```bash
mkdir -p frankenphp/certs
```

### Шаг 3 — Выпустить сертификат

```bash
mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "localhost"
```

Замените `localhost` на ваше имя сервера (например, `myapp.localhost`).

### Шаг 4 — Подключить сертификат в compose.override.yaml

```yaml
services:
  php:
    environment:
      CADDY_SERVER_EXTRA_DIRECTIVES: "tls /etc/caddy/certs/tls.pem /etc/caddy/certs/tls.key"
    volumes:
      - ./frankenphp/certs:/etc/caddy/certs:ro
```

### Шаг 5 — Перезапустить контейнер

```bash
docker compose restart php
```

---

## Отключить HTTPS (только HTTP, для локальной разработки)

```bash
SERVER_NAME=http://localhost \
MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure \
docker compose up --wait
```

После этого приложение доступно по `http://localhost`.

---

## Как работает автоматический TLS на продакшене

При запуске с реальным доменом Caddy автоматически получает сертификат от **Let's Encrypt** или **ZeroSSL** — никаких дополнительных настроек не нужно.

```bash
SERVER_NAME=your-domain.example.com docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

> Порты 80 и 443 должны быть доступны из интернета (Let's Encrypt использует их для проверки домена).