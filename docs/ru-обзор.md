# Обзор проекта petSymphony

## Стек технологий

| Уровень | Технология | Версия |
|---------|-----------|--------|
| Язык (backend) | PHP | >=8.4 |
| Backend-фреймворк | Symfony | 8.0 |
| Веб-сервер | FrankenPHP + Caddy | 1.x |
| База данных | PostgreSQL | 16 |
| ORM | Doctrine | 3.6 |
| Frontend-фреймворк | React | 18 |
| Frontend-язык | TypeScript | Latest |
| Сборщик ресурсов | Webpack Encore | 5.x |
| Шаблонизатор | Twig | 3.x |
| Контейнеризация | Docker + Docker Compose | — |
| Отладчик | XDebug | 3.x |

## Структура директорий

```
petSymphony/
├── src/                          # Исходный код приложения
│   ├── Controller/               # HTTP-контроллеры (обработчики запросов)
│   ├── Entity/                   # Doctrine-сущности (модели БД)
│   ├── Repository/               # Репозитории Doctrine
│   └── Kernel.php                # Ядро Symfony
│
├── templates/                    # Twig-шаблоны (HTML страниц)
│   └── base.html.twig            # Базовый шаблон с подключением React
│
├── assets/                       # Фронтенд-исходники
│   ├── app.ts                    # Точка входа — подключает Stimulus и React
│   ├── react/
│   │   ├── controllers/          # React-компоненты (DepositForm.tsx и др.)
│   │   └── services/
│   │       └── ApiService.ts     # HTTP-клиент для запросов к backend
│   └── styles/
│       └── app.css               # Глобальные стили
│
├── public/                       # Корень веб-сервера
│   ├── index.php                 # Точка входа Symfony (front controller)
│   └── build/                    # Webpack-сборка (генерируется автоматически)
│
├── config/                       # Конфигурация Symfony
│   ├── packages/                 # Настройки бандлов (doctrine, twig, webpack и т.д.)
│   ├── routes.yaml               # Импорт роутов (через атрибуты контроллеров)
│   └── services.yaml             # Описание сервисов
│
├── migrations/                   # Миграции базы данных (Doctrine Migrations)
│
├── frankenphp/                   # Конфигурация Docker/FrankenPHP
│   ├── Caddyfile                 # Конфиг веб-сервера Caddy
│   ├── docker-entrypoint.sh      # Скрипт инициализации контейнера
│   └── conf.d/
│       ├── 10-app.ini            # Базовые настройки PHP
│       ├── 20-app.dev.ini        # Настройки PHP для разработки (XDebug)
│       └── 20-app.prod.ini       # Настройки PHP для продакшена
│
├── var/                          # Рантайм-данные (кэш, логи)
│   ├── cache/                    # Кэш Symfony
│   └── log/                      # Логи приложения
│
├── docker-compose.yaml           # Основные сервисы (php, database)
├── compose.override.yaml         # Оверрайды для разработки (volumes, debug)
├── compose.prod.yaml             # Оверрайды для продакшена
├── Dockerfile                    # Образ PHP (multi-stage: dev / prod)
├── webpack.config.js             # Конфигурация Webpack
├── package.json                  # Node-зависимости
├── composer.json                 # PHP-зависимости
├── tsconfig.json                 # Настройки TypeScript
├── .env                          # Переменные окружения по умолчанию
└── .env.dev                      # Переменные окружения для разработки
```

## Как работает связка backend ↔ frontend

1. **PHP/Symfony** принимает HTTP-запрос и вызывает нужный **контроллер**.
2. Контроллер возвращает **Twig-шаблон** (HTML-страницу).
3. В шаблоне через функцию `react_component('НазваниеКомпонента')` подключается **React-компонент**.
4. **Webpack Encore** собирает все `assets/` в `public/build/` — именно эти файлы загружает браузер.
5. React-компоненты общаются с backend через обычные **HTTP-запросы** (fetch/axios) к Symfony-контроллерам.

## Сервисы Docker

| Сервис | Образ | Порты | Среда |
|--------|-------|-------|-------|
| `php` | FrankenPHP (кастомный) | 80, 443, 443/UDP (HTTP/3) | dev + prod |
| `database` | postgres:16-alpine | 5432 | dev + prod |
| `node` | node:22-alpine | — | только dev |

Контейнер `node` запускается автоматически в dev-окружении (`compose.override.yaml`) и следит за изменениями в `assets/`, пересобирая файлы в `public/build/`. В продакшене frontend-ресурсы собираются на этапе сборки Docker-образа (`npm run build` внутри multi-stage Dockerfile).

## Переменные окружения (.env)

| Переменная | Значение по умолчанию | Назначение |
|-----------|----------------------|-----------|
| `APP_ENV` | `dev` | Окружение: `dev`, `prod`, `test` |
| `APP_SECRET` | *(пусто, задаётся в .env.dev)* | Секретный ключ Symfony |
| `XDEBUG_MODE` | `debug` | Режим XDebug: `debug`, `off`, `profile` |
| `DATABASE_URL` | postgresql://app:!ChangeMe!@… | URL подключения к БД |
| `POSTGRES_USER` | `app` | Пользователь PostgreSQL |
| `POSTGRES_PASSWORD` | `!ChangeMe!` | Пароль PostgreSQL |
| `POSTGRES_DB` | `app` | Имя базы данных |
| `DEFAULT_URI` | `http://localhost` | Базовый URL для CLI-команд |