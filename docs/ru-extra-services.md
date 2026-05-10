# Дополнительные сервисы

## Автоматическое подключение через Symfony Flex

Symfony Docker расширяем: при установке совместимых Composer-пакетов через Symfony Flex рецепты **автоматически** обновляют `Dockerfile` и `compose.yaml` для поддержки новых сервисов.

> **Важно:** Устанавливайте пакеты **внутри контейнера** в режиме разработки, чтобы рецепты применились корректно:
>
> ```bash
> docker compose exec php composer require <пакет>
> ```

---

## Поддерживаемые пакеты

| Пакет | Что добавляет |
|-------|--------------|
| `symfony/orm-pack` | Сервис PostgreSQL |
| `symfony/mercure-bundle` | Модуль Mercure (встроен в Caddy) |
| `symfony/panther` | Chromium и WebDriver-драйверы для e2e-тестов |
| `symfony/mailer` | Сервис Mailpit (SMTP-сервер для разработки) |
| `blackfireio/blackfire-symfony-meta` | Сервис Blackfire (профилировщик) |

> **После изменения Dockerfile пересоберите образ:**
>
> ```bash
> docker compose build --pull --no-cache
> docker compose up --wait
> ```

---

## Пример: добавить почтовый сервер (Mailpit)

```bash
docker compose exec php composer require symfony/mailer
```

После применения рецепта в `compose.yaml` появится сервис `mailer`. Веб-интерфейс Mailpit будет доступен по адресу `http://localhost:8025`.

---

## Пример: добавить Redis вручную

Если нужного пакета нет в списке выше, добавьте сервис вручную в `compose.override.yaml`:

```yaml
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

И в `compose.yaml` — зависимость для PHP:

```yaml
services:
  php:
    depends_on:
      - redis
```

Установите PHP-расширение в `Dockerfile`:

```dockerfile
RUN install-php-extensions redis
```

---

## Пример: добавить RabbitMQ вручную

```yaml
# compose.override.yaml
services:
  rabbitmq:
    image: rabbitmq:3-management-alpine
    ports:
      - "5672:5672"
      - "15672:15672"   # Web UI
    environment:
      RABBITMQ_DEFAULT_USER: app
      RABBITMQ_DEFAULT_PASS: app
```

Веб-интерфейс: `http://localhost:15672` (логин: `app` / `app`).