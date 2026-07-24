#!/bin/sh
# Бэкап PostgreSQL: pg_dump -Fc (сжатый custom-формат, селективное восстановление).
#
# Режимы:
#   backup.sh        — вечный цикл: дамп раз в BACKUP_INTERVAL секунд (сутки)
#   backup.sh once   — один дамп и выход (ручной запуск, dev-проверки)
#
# Ротация: дневные BACKUP_KEEP_DAILY, недельные (по воскресеньям)
# BACKUP_KEEP_WEEKLY, месячные (1-е число) BACKUP_KEEP_MONTHLY.
# Файл /backups/last_success — метка времени последнего успеха (мониторинг).
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
BACKUP_INTERVAL="${BACKUP_INTERVAL:-86400}"
BACKUP_KEEP_DAILY="${BACKUP_KEEP_DAILY:-7}"
BACKUP_KEEP_WEEKLY="${BACKUP_KEEP_WEEKLY:-4}"
BACKUP_KEEP_MONTHLY="${BACKUP_KEEP_MONTHLY:-3}"
APP_GIT_SHA="${APP_GIT_SHA:-nogit}"

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/monthly"

make_dump() {
    stamp="$(date -u +%Y%m%d-%H%M%S)"
    file="$BACKUP_DIR/daily/app-$stamp-$APP_GIT_SHA.dump"
    tmp="$file.part"

    echo "[backup] $(date -u +%FT%TZ) dumping to $file"

    # .part защищает от чтения недописанного дампа; PG* переменные — из окружения
    if pg_dump -Fc --no-owner --no-privileges -f "$tmp"; then
        mv "$tmp" "$file"
        date -u +%FT%TZ > "$BACKUP_DIR/last_success"

        # Воскресенье — копия в weekly; первое число — в monthly
        [ "$(date -u +%u)" = "7" ] && cp "$file" "$BACKUP_DIR/weekly/"
        [ "$(date -u +%d)" = "01" ] && cp "$file" "$BACKUP_DIR/monthly/"

        rotate "$BACKUP_DIR/daily" "$BACKUP_KEEP_DAILY"
        rotate "$BACKUP_DIR/weekly" "$BACKUP_KEEP_WEEKLY"
        rotate "$BACKUP_DIR/monthly" "$BACKUP_KEEP_MONTHLY"

        echo "[backup] done: $(du -h "$file" | cut -f1)"
    else
        rm -f "$tmp"
        echo "[backup] FAILED $(date -u +%FT%TZ)" >&2
        return 1
    fi
}

rotate() {
    dir="$1"; keep="$2"
    # Имена содержат UTC-дату — лексикографический порядок хронологичен
    ls -1 "$dir"/*.dump 2>/dev/null | sort | head -n -"$keep" | while read -r old; do
        echo "[backup] rotate: removing $old"
        rm -f "$old"
    done
}

if [ "${1:-}" = "once" ]; then
    make_dump
    exit 0
fi

echo "[backup] loop started: every ${BACKUP_INTERVAL}s, keep ${BACKUP_KEEP_DAILY}d/${BACKUP_KEEP_WEEKLY}w/${BACKUP_KEEP_MONTHLY}m"

while :; do
    make_dump || true # неудача не убивает цикл — попробуем в следующий раз
    sleep "$BACKUP_INTERVAL"
done
