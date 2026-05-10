# Документация petSymphony (на русском)

## Критически важные разделы

| # | Файл | Содержание |
|---|------|-----------|
| 0 | [ru-00-развертывание.md](ru-00-развертывание.md) | Первый запуск, Docker, сборка ресурсов, команды |
| 1 | [ru-01-debug.md](ru-01-debug.md) | XDebug, PhpStorm, VS Code, CLI-отладка |
| 2 | [ru-02-роутинг.md](ru-02-роутинг.md) | Создание роутов, контроллеры, параметры URL |
| 3 | [ru-03-страницы.md](ru-03-страницы.md) | Twig-шаблоны, React-компоненты, HTTP-запросы |

## Проект и стек

| Файл | Содержание |
|------|-----------|
| [ru-обзор.md](ru-обзор.md) | Стек технологий, структура директорий, переменные окружения |
| [ru-база-данных.md](ru-база-данных.md) | Entity, миграции, работа с данными через Doctrine |
| [ru-webpack.md](ru-webpack.md) | Сборка JS/TS/CSS через Webpack Encore |

## Настройка инфраструктуры

| Файл | Содержание |
|------|-----------|
| [ru-options.md](ru-options.md) | Переменные Docker, порты, параметры Caddy / Mercure |
| [ru-tls.md](ru-tls.md) | HTTPS: добавить CA в доверенные, mkcert, отключить HTTPS |
| [ru-extra-services.md](ru-extra-services.md) | Добавление Redis, RabbitMQ, Mailpit и других сервисов |
| [ru-mysql.md](ru-mysql.md) | Замена PostgreSQL на MySQL |
| [ru-alpine.md](ru-alpine.md) | Alpine Linux вместо Debian |

## Деплой и сопровождение

| Файл | Содержание |
|------|-----------|
| [ru-production.md](ru-production.md) | Деплой на сервер, HTTPS, секреты, отличия prod от dev |
| [ru-makefile.md](ru-makefile.md) | Шпаргалка make-команд |
| [ru-existing-project.md](ru-existing-project.md) | Подключение Docker к существующему Symfony-проекту |
| [ru-updating.md](ru-updating.md) | Обновление Docker-шаблона из upstream |
| [ru-troubleshooting.md](ru-troubleshooting.md) | Решение типичных проблем |

---

## Быстрый старт

```bash
# 1. Клонировать и перейти в директорию
git clone <url> && cd petSymphony

# 2. Запустить контейнеры (с XDebug — он уже включён в .env)
docker compose up --wait

# 3. Собрать ресурсы
docker compose exec php sh -c "npm install && npm run dev"

# 4. Открыть https://localhost/
```

## Самые нужные команды

```bash
docker compose up --wait                              # Запустить
docker compose down --remove-orphans                  # Остановить
docker compose exec php bin/console debug:router      # Список роутов
docker compose exec php bin/console cache:clear       # Сбросить кэш
docker compose exec php bin/console make:entity       # Создать Entity
docker compose exec php bin/console make:controller X # Создать контроллер
docker compose exec php bin/console doctrine:migrations:migrate
npm run watch                                         # Следить за изменениями ресурсов
```