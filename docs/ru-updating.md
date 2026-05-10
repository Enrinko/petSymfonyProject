# Обновление Docker-шаблона Symfony

Если в upstream-репозитории [symfony-docker](https://github.com/dunglas/symfony-docker) вышли обновления и вы хотите их получить в своём проекте — используйте инструмент [template-sync](https://github.com/coopTilleuls/template-sync).

---

## Синхронизация через template-sync

```bash
curl -sSL https://raw.githubusercontent.com/coopTilleuls/template-sync/main/template-sync.sh \
  | sh -s -- https://github.com/dunglas/symfony-docker
```

Скрипт:
1. Загружает последние изменения из upstream
2. Применяет их к вашему проекту через git cherry-pick

---

## Разрешение конфликтов

Если возникли конфликты слияния:

1. Откройте конфликтные файлы и разрешите их вручную
2. Добавьте файлы в индекс:
   ```bash
   git add .
   ```
3. Продолжите cherry-pick:
   ```bash
   git cherry-pick --continue
   ```

---

## Дополнительные параметры

Подробная документация по расширенному использованию: [github.com/coopTilleuls/template-sync](https://github.com/coopTilleuls/template-sync#template-sync).