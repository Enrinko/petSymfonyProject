# Решение типичных проблем

## Права доступа к файлам (Linux)

После первой установки на Linux некоторые файлы могут быть созданы от имени root-пользователя Docker и недоступны для редактирования.

**Решение:**

```bash
docker compose run --rm php chown -R $(id -u):$(id -g) .
```

---

## Приложение показывает phpinfo() вместо страницы

**Причина:** Запущен dev-образ вместо prod-образа, или образ не был пересобран.

**Решение:** Пересобрать образ с правильными файлами:

```bash
# Для разработки
docker compose build --pull --no-cache
docker compose up --wait

# Для продакшена
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

---

## Ошибка 500 или белый экран

**1. Проверить логи PHP:**

```bash
docker compose logs php
```

**2. Проверить логи Symfony:**

```bash
docker compose exec php cat var/log/dev.log
```

**3. Сбросить кэш:**

```bash
docker compose exec php bin/console cache:clear
```

---

## XDebug не подключается к IDE

**Проверить, что XDebug запущен:**

```bash
docker compose exec php php --version
# Должна быть строка: with Xdebug v3.x.x
```

**Проверить XDEBUG_MODE:**

```bash
docker compose exec php php -r "var_dump(ini_get('xdebug.mode'));"
# Должно вернуть: string(5) "debug"
```

**Типичные причины:**

| Проблема | Решение |
|---------|---------|
| `XDEBUG_MODE=off` в `.env.local` | Удалить или изменить на `debug` |
| Порт 9003 занят | Освободить порт или изменить в настройках IDE |
| Неверный маппинг путей | Убедиться, что `/app` → корень проекта |
| Брандмауэр блокирует порт 9003 | Открыть порт 9003 в настройках хоста |

---

## Порты 80/443 заняты

**Проверить, какой процесс занимает порт:**

**Windows:**

```powershell
netstat -ano | findstr :80
# Найти PID и остановить: taskkill /PID <pid> /F
```

**Linux/macOS:**

```bash
lsof -i :80
```

**Альтернатива:** Изменить порты в `compose.override.yaml`:

```yaml
services:
  php:
    ports:
      - "8080:80"   # Вместо 80
      - "8443:443"  # Вместо 443
```

---

## База данных не подключается

**1. Проверить, что контейнер database запущен:**

```bash
docker compose ps
```

**2. Проверить healthcheck:**

```bash
docker compose exec database pg_isready -U app
```

**3. Проверить переменные окружения:**

```bash
docker compose exec php env | grep DATABASE
```

**4. Проверить строку подключения в .env:**

```dotenv
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app?serverVersion=16&charset=utf8"
```

> Внутри Docker-сети хост базы данных — `database` (имя сервиса), а не `127.0.0.1`.

---

## Webpack: ресурсы не обновляются

**Пересобрать:**

```bash
npm run dev
```

**Или следить за изменениями:**

```bash
npm run watch
```

**Если `public/build/` устарела:**

```bash
rm -rf public/build/
npm run dev
```

---

## Ошибка "Class not found" в Symfony

```bash
docker compose exec php composer dump-autoload
```

---

## Миграции не применяются

```bash
# Посмотреть статус миграций
docker compose exec php bin/console doctrine:migrations:status

# Применить все ожидающие миграции
docker compose exec php bin/console doctrine:migrations:migrate

# Если схема расходится с миграциями
docker compose exec php bin/console doctrine:schema:validate
```

---

## Полностью пересобрать проект

```bash
# Остановить всё
docker compose down --volumes --remove-orphans

# Удалить кэш
rm -rf var/cache/*

# Пересобрать
docker compose build --pull --no-cache
docker compose up --wait
```

> `--volumes` также удалит данные PostgreSQL — используйте осторожно.