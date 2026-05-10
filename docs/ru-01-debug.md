# 1. Включение и запуск проекта с debug

## Как работает XDebug в этом проекте

XDebug предустановлен в dev-образе FrankenPHP. По умолчанию он **отключён** для производительности.
Режим управляется переменной `XDEBUG_MODE`.

В файле `.env` уже выставлено:

```dotenv
XDEBUG_MODE=debug
```

Это значение подхватывается `compose.override.yaml`:

```yaml
environment:
  XDEBUG_MODE: "${XDEBUG_MODE:-off}"
```

Т.е. если переменная не задана — XDebug выключен. Если задана (как у нас в `.env`) — включён.

---

## Запуск с отладкой

### Вариант 1 — через .env (рекомендуется для разработки)

В файле `.env` уже прописано `XDEBUG_MODE=debug`. Просто запускайте как обычно:

```bash
docker compose up --wait
```

### Вариант 2 — через переменную окружения (разово)

**Windows (PowerShell):**

```powershell
$env:XDEBUG_MODE="debug"
docker compose up --wait
$env:XDEBUG_MODE=""
```

**Windows (cmd):**

```cmd
set XDEBUG_MODE=debug&& docker compose up --wait&set XDEBUG_MODE=
```

**Linux / macOS:**

```bash
XDEBUG_MODE=debug docker compose up --wait
```

### Вариант 3 — отключить XDebug (если тормозит)

```dotenv
# .env.local
XDEBUG_MODE=off
```

---

## Настройка PhpStorm

### Шаг 1 — Создать конфигурацию PHP Debug Server

1. Открыть `Settings` → `PHP` → `Servers`
2. Нажать `+` и создать сервер:

   | Поле | Значение |
   |------|---------|
   | Name | `symfony` |
   | Host | `localhost` |
   | Port | `443` |
   | Debugger | `Xdebug` |

3. Включить **Use path mappings**
4. Указать маппинг:

   | Локальный путь | Путь в контейнере |
   |---------------|-----------------|
   | `C:\projects\petSymphony` (или ваш путь) | `/app` |

### Шаг 2 — Начать прослушивание

`Run` → `Start Listening for PHP Debug Connections` (иконка телефонной трубки в верхней панели)

### Шаг 3 — Запустить отладку в браузере

**Способ A — Через URL-параметр:**

```
https://localhost/?XDEBUG_SESSION=PHPSTORM
```

**Способ B — Через браузерное расширение:**

Установите расширение [Xdebug Helper](https://xdebug.org/docs/step_debug#browser-extensions) для вашего браузера. Кликните на иконку и выберите `Debug`.

---

## Настройка VS Code

### Шаг 1 — Установить расширение

Установите [PHP Tools for VS Code](https://marketplace.visualstudio.com/items?itemName=DEVSENSE.phptools-vscode) из магазина расширений.

### Шаг 2 — Создать конфигурацию запуска

Создайте файл `.vscode/launch.json` в корне проекта:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/app": "${workspaceFolder}"
      }
    }
  ]
}
```

### Шаг 3 — Начать отладку

`Run` → `Start Debugging` (или `F5`) → выбрать `Listen for Xdebug`.

Затем откройте страницу в браузере с расширением Xdebug Helper в режиме Debug.

---

## Отладка CLI-команд (Symfony Console)

```bash
XDEBUG_SESSION=1 PHP_IDE_CONFIG="serverName=symfony" \
docker compose exec php php bin/console <команда>
```

> `serverName=symfony` должен совпадать с именем сервера, созданного в PhpStorm (шаг 1).

---

## Проверка установки XDebug

```bash
docker compose exec php php --version
```

В выводе должна присутствовать строка:

```
with Xdebug v3.x.x, ...
```

---

## Режимы XDebug

| Значение `XDEBUG_MODE` | Описание |
|------------------------|---------|
| `off` | XDebug отключён (максимальная скорость) |
| `debug` | Пошаговая отладка (step debugging) |
| `profile` | Профилирование (анализ производительности) |
| `trace` | Трассировка вызовов функций |
| `coverage` | Покрытие кода тестами |
| `develop` | Улучшенный вывод ошибок и var_dump |

Можно комбинировать через запятую:

```dotenv
XDEBUG_MODE=debug,develop
```

---

## Конфигурация XDebug (frankenphp/conf.d/20-app.dev.ini)

```ini
xdebug.client_host = host.docker.internal
```

Эта настройка направляет XDebug к хост-машине через Docker-мост. На Linux также нужен `extra_hosts` в `compose.override.yaml` (уже настроен).