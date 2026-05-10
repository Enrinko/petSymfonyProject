# Makefile — шпаргалка команд

Makefile — это набор сокращений для часто используемых команд Docker и Symfony.

> **Windows:** Для использования `make` установите [Chocolatey](https://chocolatey.org/) и затем `choco install make`,
> или используйте [Cygwin](http://cygwin.com). Также работает в Git Bash или WSL2.

## Шаблон Makefile

Создайте файл `Makefile` в корне проекта:

```makefile
# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Выводит этот справочный экран
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Собрать Docker-образы
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Запустить контейнеры в фоновом режиме
	@$(DOCKER_COMP) up --detach

start: build up ## Собрать и запустить контейнеры

down: ## Остановить контейнеры
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Следить за логами в реальном времени
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Войти в shell контейнера php
	@$(PHP_CONT) sh

bash: ## Войти в bash контейнера php (с историей команд)
	@$(PHP_CONT) bash

test: ## Запустить тесты (параметр c= для опций phpunit)
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Запустить composer (параметр c= для команды)
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Установить зависимости из composer.lock
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## Запустить Symfony console (параметр c= для команды)
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Сбросить кэш
cc: sf
```

> **Важно:** В Makefile используются **табы**, не пробелы. Убедитесь, что ваш редактор не заменяет их.
> Добавьте в `.editorconfig`:
> ```
> [Makefile]
> indent_style = tab
> ```

## Справочник команд

| Команда | Описание |
|---------|---------|
| `make` | Показать все доступные команды |
| `make build` | Пересобрать Docker-образы |
| `make up` | Запустить контейнеры |
| `make start` | Собрать и запустить |
| `make down` | Остановить контейнеры |
| `make logs` | Смотреть логи |
| `make sh` | Shell в контейнере php |
| `make bash` | Bash в контейнере php |
| `make sf c=about` | `bin/console about` |
| `make sf c=debug:router` | Список роутов |
| `make sf c=cache:clear` | Очистить кэш |
| `make sf c=make:entity` | Создать Entity |
| `make sf c=make:migration` | Создать миграцию |
| `make sf c="doctrine:migrations:migrate"` | Применить миграции |
| `make composer c='req symfony/orm-pack'` | Установить пакет |
| `make cc` | Сбросить кэш Symfony |
| `make test` | Запустить тесты |
| `make test c="--group e2e"` | Тесты с опциями |