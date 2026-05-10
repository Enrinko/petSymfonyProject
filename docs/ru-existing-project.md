# Подключение Docker-инфраструктуры к существующему проекту

Если у вас уже есть Symfony-проект и вы хотите добавить к нему эту Docker-конфигурацию.

---

## Шаг 1 — Скачать skeleton

Скачайте [symfony-docker](https://github.com/dunglas/symfony-docker).

Если вы клонировали репозиторий — **не копируйте** папку `.git`, чтобы не конфликтовать с вашим существующим репозиторием.

**Через git archive (только файлы без .git):**

```bash
git archive --format=tar HEAD | tar -xC my-existing-project/
```

**Через ZIP-архив:**

```bash
cp -Rp symfony-docker/. my-existing-project/
```

---

## Шаг 2 — Включить поддержку Docker в Symfony Flex

```bash
composer config --json extra.symfony.docker 'true'
```

---

## Шаг 3 — Установить FrankenPHP runtime (для Symfony ≤ 7.3)

Worker-режим FrankenPHP включён по умолчанию. Для Symfony версий до 7.4 требуется дополнительный пакет:

```bash
composer require runtime/frankenphp-symfony
```

Затем обновите `frankenphp/Caddyfile`:

```diff
 worker {
     file ./public/index.php
+    env APP_RUNTIME Runtime\FrankenPhpSymfony\Runtime
     {$FRANKENPHP_WORKER_CONFIG}
 }
```

> Чтобы отключить worker-режим совсем — удалите директиву `worker` из блока `frankenphp` в `Caddyfile`.

---

## Шаг 4 — Переустановить рецепты

Это обновит Docker-файлы в соответствии с установленными пакетами:

```bash
rm symfony.lock
composer recipes:install --force --verbose
```

---

## Шаг 5 — Проверить изменения

```bash
git diff
```

Просмотрите изменения и откатите те, которые вам не нужны.

---

## Шаг 6 — Собрать и запустить

```bash
docker compose build --pull --no-cache
docker compose up --wait
```

Откройте `https://localhost` — Docker-конфигурация готова.