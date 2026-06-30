# Внешние проекты (Оркестр)

petSymphony умеет подключать **другие проекты (на Laravel и не только)** как
**git-submodules** и показывать их в боковом меню **«Оркестр → Проекты»**
(`/projects`). Каждый проект остаётся в своём репозитории и со своим Docker;
с основным стеком он делит **только сеть** и открывается через основной Caddy
по поддомену `https://<key>.localhost`.

Главный принцип: **репозитории сабмодулей не меняем.** Вся обвязка живёт в
petSymphony — на каждый проект три маленьких артефакта, связанных одним ключом
`<key>` (он же имя папки `projects/<key>` и поддомен `<key>.localhost`):

| Артефакт | Файл | Зачем |
| --- | --- | --- |
| Запись в реестре | `config/projects.yaml` | карточка на странице `/projects` |
| Caddy-сниппет | `frankenphp/projects/<key>.caddy` | reverse-proxy `<key>.localhost` → контейнер |
| Compose-override | `docker/projects/<key>.override.yaml` | подключает контейнер к сети `petsymphony_default` |

Шаблоны лежат рядом: `frankenphp/projects/example.caddy.dist` и
`docker/projects/example.override.yaml.dist`.

> В prod поддоменный импорт отключён (переменная `CADDY_EXTRA_CONFIG` пуста),
> а `projects/` и `.gitmodules` исключены из Docker-образа. Это локальная/dev
> оркестрация — поэтому список проектов берётся из `config/projects.yaml`
> (он коммитится), а не из `.gitmodules`.

---

## Как добавить проект

Пусть ключ проекта — `app1`, веб-сервис в его compose называется `app` и слушает порт `80`.

### 1. Подключить как submodule

```bash
git submodule add <repo-url> projects/app1
```

Создастся/дополнится `.gitmodules`. На другой машине после клона petSymphony:

```bash
git submodule update --init --recursive
```

### 2. Caddy-сниппет — `frankenphp/projects/app1.caddy`

```caddyfile
app1.localhost {
	reverse_proxy app1-php:80
}
```

`app1-php` — стабильный сетевой алиас контейнера (задаётся в шаге 3). Порт `80`
поменяйте на тот, который слушает веб-контейнер сабмодуля (nginx / FrankenPHP /
`php artisan serve` и т.п.).

### 3. Compose-override — `docker/projects/app1.override.yaml`

```yaml
services:
  app:                       # имя веб-сервиса из compose сабмодуля
    networks:
      default: {}            # сохраняем собственную сеть приложения
      shared:
        aliases:
          - app1-php         # имя, по которому проксирует основной Caddy

networks:
  shared:
    external: true
    name: petsymphony_default
```

### 4. Запись в реестр — `config/projects.yaml`

```yaml
parameters:
    app.external_projects:
        - key: app1
          name: 'App One'
          description: 'Короткое описание'
          url: 'https://app1.localhost'
          repo: 'https://github.com/you/app1'   # опционально
          stack: 'Laravel'
          icon: '🎻'
```

---

## Запуск и остановка

Сначала поднимается основной стек (он создаёт сеть `petsymphony_default`),
затем — приложения, которые к ней цепляются.

```bash
docker compose up --wait        # основной стек

# Все настроенные проекты разом (+ перечитать Caddy):
sh bin/projects up
sh bin/projects down            # остановить все
sh bin/projects list            # что настроено
```

Вручную один проект:

```bash
docker compose -p app1 \
  -f projects/app1/compose.yaml \
  -f docker/projects/app1.override.yaml up -d
docker compose restart php      # подхватить новый/изменённый Caddy-сниппет
```

> **PowerShell (Windows):** многострочный `\`-перенос не работает — пишите команду
> одной строкой:
> `docker compose -p app1 -f projects/app1/compose.yaml -f docker/projects/app1.override.yaml up -d`

После любого добавления/правки `frankenphp/projects/*.caddy` нужен
`docker compose restart php` — FrankenPHP `--watch` следит за PHP, но не за Caddyfile.

---

## Доступ по `https://<key>.localhost`

- `*.localhost` современные браузеры (Chrome/Edge/Firefox) сами резолвят в loopback —
  правки `hosts` не нужны. Для `curl`/прочего пропишите вручную:
  `C:\Windows\System32\drivers\etc\hosts` → `127.0.0.1 app1.localhost`.
- В dev Caddy выдаёт **самоподписанный** сертификат на поддомен → браузер покажет
  предупреждение. Варианты:
  - принять предупреждение (это локальный dev);
  - установить корневой CA Caddy в доверенные (убирает предупреждение для всех поддоменов);
  - временно работать по HTTP — в сниппете `http://app1.localhost { … }` и
    `url: 'http://app1.localhost'` в реестре.

---

## Подключённые проекты

Уже подключены два сабмодуля (ключ = папка `projects/<key>` = поддомен `<key>.localhost`):

### `yandex-maps-parser` — Яндекс.Карты, парсер отзывов
- Стек: Laravel 13 (API) + Node/Playwright (скрапер) + Vue 3 (SPA); свои MySQL и Redis.
- Веб-вход: сервис **`nginx`** (порт 80) → алиас `yandex-maps-parser-web`.
- Запускается на дефолтах из compose (в нём задан дефолтный `APP_KEY`) — отдельный `.env` не обязателен.
- **Конфликт портов:** его `nginx` публикует `${APP_PORT:-8080}:80`, а 8080 занят `swagger-ui`.
  Задай `APP_PORT` (напр. 8082) в `projects/yandex-maps-parser/.env` (доступ всё равно идёт через Caddy).
- Для cookie-авторизации через поддомен: добавь `yandex-maps-parser.localhost` в `SANCTUM_STATEFUL_DOMAINS`/`SESSION_DOMAIN`
  и выставь `APP_URL=https://yandex-maps-parser.localhost`.

### `foodninja` — Short Links (Laravel 11 + Filament)
- ⚠️ Репозиторий называется **FoodNinja**, но содержит сервис коротких ссылок **«Short Links»**.
  Имя карточки правится одной строкой в `config/projects.yaml`.
- Стек: Laravel 11 + Filament v3 на **Laravel Sail**; своя Postgres. Кабинет: `/admin`.
- Веб-вход: сервис **`laravel.test`** (порт 80) → алиас `foodninja-web`.
- **Нужен `.env`:** `cp projects/foodninja/.env.example projects/foodninja/.env`, затем сгенерировать `APP_KEY`
  и задать `WWWUSER`/`WWWGROUP` (Sail).
- **Конфликт портов:** публикует `${APP_PORT:-80}:80` и `${FORWARD_DB_PORT:-5432}:5432` — оба заняты petSymphony.
  Задай в `.env`: `APP_PORT=8081`, `FORWARD_DB_PORT=5433`. Выставь `APP_URL=https://foodninja.localhost`.

Поднять оба (после `docker compose up --wait`): `sh bin/projects up`.

## Диагностика

| Симптом | Причина / решение |
| --- | --- |
| `502 Bad Gateway` | алиас или порт в `.caddy` не совпадает с контейнером. Проверьте `aliases: [app1-php]` и порт. |
| Поддомен не открывается / «site not found» | не перечитан Caddy → `docker compose restart php`. |
| `network petsymphony_default not found` | не поднят основной стек или другое имя проекта. Сначала `docker compose up --wait`. |
| Папка `projects/app1` пустая | сабмодуль не выгружен → `git submodule update --init --recursive`. |
| Карточки нет на `/projects` | нет записи в `config/projects.yaml` или не сброшен кэш: `docker compose exec php bin/console cache:clear`. |
