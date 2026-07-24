# Бэкапы PostgreSQL и восстановление

База — единственное хранилище данных CRM. Здесь описано, как устроены
автоматические бэкапы и, главное, **как восстановиться**.

## Как устроено

В прод-стеке (`compose.prod.yaml`) крутится сервис `backup`:

- раз в сутки `pg_dump -Fc` (сжатый custom-формат — позволяет селективное
  восстановление отдельных таблиц) в volume `backups_data`;
- имя файла: `app-<UTC-дата>-<git-sha>.dump` — по sha видно, к какой версии
  схемы дамп относится;
- ротация: **7 дневных**, **4 недельных** (копия по воскресеньям),
  **3 месячных** (копия 1-го числа);
- `/backups/last_success` — метка времени последнего успешного дампа;
  healthcheck сервиса становится **unhealthy, если бэкапа нет больше 48 часов**;
- git-sha приложения передаётся через env `APP_GIT_SHA` при деплое
  (`APP_GIT_SHA=$(git rev-parse --short HEAD) docker compose ... up -d`).

Параметры (env сервиса `backup`): `BACKUP_INTERVAL` (сек, по умолчанию 86400),
`BACKUP_KEEP_DAILY|WEEKLY|MONTHLY`.

> RPO — сутки (дамп-подход, не WAL-архивация): для CRM школы приемлемо,
> сложность на порядок ниже. Понадобится жёстче — отдельная задача про PITR.

## ВАЖНО: бэкап на том же диске защищает не от всего

Volume `backups_data` живёт на том же хосте, что и база. Сбой диска унесёт
обоих. Рекомендация: выгружать дампы наружу (S3-совместимое хранилище,
rclone на другой хост) — например, хост-cron:

```bash
docker run --rm -v petsymphony_backups_data:/backups:ro rclone/rclone \
    copy /backups/daily remote:petsymphony-backups/daily
```

## Ручной дамп (в любой момент)

```bash
# prod
docker compose -f compose.yaml -f compose.prod.yaml exec backup sh /backup.sh once

# dev (одноразовый контейнер, дамп в ./var/backups)
docker compose exec database sh -c 'pg_dump -U app -Fc app' > var/backups/manual-$(date +%Y%m%d).dump
```

## Восстановление (runbook)

1. **Остановить приложение** — чтобы никто не писал в базу:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml stop php worker
   ```

2. **Выбрать дамп** (посмотреть, что есть):

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml run --rm backup ls -lh /backups/daily
   ```

3. **Восстановить в чистую БД** (пересоздание схемы `--clean --if-exists`):

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml exec database \
       pg_restore -U app -d app --clean --if-exists --no-owner --no-privileges \
       /dev/stdin < ПУТЬ/К/ДАМПУ.dump
   ```

   Если дамп внутри volume бэкапов, проще через сервис backup:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml exec backup \
       pg_restore --clean --if-exists --no-owner --no-privileges \
       -d "postgresql://app:$POSTGRES_PASSWORD@database:5432/app" \
       /backups/daily/app-XXXXXXXX.dump
   ```

4. **Догнать миграции**, если код новее дампа (sha в имени файла подскажет):

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml exec php \
       bin/console doctrine:migrations:migrate --no-interaction
   ```

5. **Поднять приложение и проверить**:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml start php worker
   curl -fk https://ХОСТ/healthz
   ```

## Проверка восстановимости — раз в месяц

Непроверенный бэкап равен отсутствующему:

```bash
sh bin/verify-backup                     # последний дамп из volume prod-стека
sh bin/verify-backup путь/к/файлу.dump   # конкретный файл
```

Скрипт поднимает одноразовый PostgreSQL, восстанавливает дамп и делает
smoke-запросы (количество пользователей/клиентов/таблиц). Контейнер убирает
за собой в любом исходе.
