# Замена PostgreSQL на MySQL

По умолчанию проект использует **PostgreSQL 16**. Если вы предпочитаете MySQL — следуйте инструкции ниже.

---

## 1. Установить ORM-пакет

```bash
docker compose exec php composer require symfony/orm-pack
```

---

## 2. Изменить образ в compose.yaml

Найдите блок `database` и замените конфигурацию:

```yaml
# Было (PostgreSQL):
database:
  image: postgres:16-alpine
  environment:
    POSTGRES_DB: ${POSTGRES_DB:-app}
    POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
    POSTGRES_USER: ${POSTGRES_USER:-app}
  healthcheck:
    test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-app}", "-U", "${POSTGRES_USER:-app}"]
    timeout: 5s
    retries: 5
    start_period: 60s
  volumes:
    - database_data:/var/lib/postgresql/data:rw

# Стало (MySQL):
database:
  image: mysql:8
  environment:
    MYSQL_DATABASE: ${MYSQL_DATABASE:-app}
    MYSQL_RANDOM_ROOT_PASSWORD: "true"
    MYSQL_PASSWORD: ${MYSQL_PASSWORD:-!ChangeMe!}
    MYSQL_USER: ${MYSQL_USER:-app}
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    timeout: 5s
    retries: 5
    start_period: 60s
  volumes:
    - database_data:/var/lib/mysql:rw
```

---

## 3. Обновить порт в compose.override.yaml

```yaml
# Было:
database:
  ports:
    - "5432"

# Стало:
database:
  ports:
    - "3306"
```

---

## 4. Обновить PHP-расширение в Dockerfile

```dockerfile
# Было:
RUN install-php-extensions pdo_pgsql

# Стало:
RUN install-php-extensions pdo_mysql
```

---

## 5. Обновить строку подключения в .env

```dotenv
# Было:
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"

# Стало:
DATABASE_URL="mysql://app:!ChangeMe!@database:3306/app?serverVersion=8&charset=utf8mb4"
```

---

## 6. Пересобрать и запустить

```bash
docker compose down --remove-orphans
docker compose build --pull --no-cache
docker compose up --wait
```

---

## 7. Проверить подключение

```bash
docker compose exec php bin/console dbal:run-sql -q "SELECT 1" && echo "OK" || echo "Ошибка подключения"
```

---

## Сравнение PostgreSQL и MySQL

| Аспект | PostgreSQL (дефолт) | MySQL |
|-------|---------------------|-------|
| Порт | 5432 | 3306 |
| PHP-расширение | `pdo_pgsql` | `pdo_mysql` |
| Docker-образ | `postgres:16-alpine` | `mysql:8` |
| Тип данных JSON | Нативный | Начиная с 5.7 |
| Производительность | Лучше для сложных запросов | Лучше для простых операций |