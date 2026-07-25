# Глобальный поиск: Elasticsearch + фолбэк ILIKE

Палитра Ctrl+K (`/api/search?q=…`) ищет по клиентам (имя, email, телефон),
тегам и заметкам. С задачи `elasticsearch-search` движок под капотом —
Elasticsearch с прозрачным фолбэком на PostgreSQL ILIKE.

## Архитектура

```
SearchController → GlobalSearchHandler (trim, min 2 символа)
                     └─ SearchProviderInterface
                          └─ FallbackSearchProvider
                               ├─ ElasticsearchSearchProvider   ← основной
                               └─ DatabaseSearchProvider (ILIKE) ← фолбэк
```

- **`ElasticsearchSearchProvider`** — multi_match с `fuzziness=AUTO`
  (опечаткоустойчивость: «Ивонов» находит «Иванова»), русский анализатор
  (морфология/стемминг), edge-ngram по имени («поиск по мере ввода»),
  сниппеты заметок из ES highlight. Схема **search-then-hydrate**: ES отдаёт
  только id и фрагменты, данные для отображения поднимаются из БД — индекс
  не хранит денормализованных полей, пользователь никогда не видит устаревших
  имён.
- **`DatabaseSearchProvider`** — прежний ILIKE + pg_trgm. Работает всегда;
  на него же смотрят тесты и CI (там ES не поднимается).
- **`FallbackSearchProvider`** — при любом сбое ES пишет `warning` в лог
  и молча повторяет запрос через ILIKE. Пользователь сбоя не видит.
- **Теги** в ES не индексируются: их десятки, ILIKE по ним не деградирует.

Видимость та же, что и в списках: преподаватель ищет только по своим
клиентам/заметкам (фильтр `owner_id` в ES-запросе **плюс** повторная проверка
владельца после подъёма из БД — страховка от устаревшего индекса), архивные
клиенты ищутся и помечаются.

## Индексы

| Индекс | Поля | Анализ |
|---|---|---|
| `clients` | name, email, phone, owner_id, archived, created_at | `russian`; `name.prefix` — edge_ngram 2–15 |
| `notes` | content, owner_id, client_id, created_at | `russian` |

Маппинги явные (`dynamic: strict`), живут в `App\Infrastructure\Search\SearchDocuments`.

## Индексация

Синхронно-через-очередь: use-case-хендлеры (создание/изменение/архив клиента,
CSV-импорт, CRUD заметок) после `save()` ставят сообщение в Messenger
(`IndexClientMessage` / `IndexNoteMessage` / `RemoveNoteFromIndexMessage`,
транспорт `async`), а воркер поднимает свежую сущность из БД и пишет в индекс.
Doctrine-транспорт живёт в той же БД, поэтому dispatch внутри транзакции
(например, импорта) откатывается вместе с ней.

Недоступный ES роняет обработку сообщения → штатные ретраи Messenger
(3 попытки), затем `failed`-транспорт (`messenger:failed:show`).

## Полная переиндексация

```bash
docker compose exec php bin/console app:search:reindex
```

Пересоздаёт оба индекса и заливает все данные bulk-чанками по 500. Нужна:

- после первого запуска (индексов ещё нет);
- после изменения маппингов в `SearchDocuments`;
- после потери/пересоздания тома `elasticsearch_data`;
- как страховка от рассинхрона (можно ночным cron'ом).

Пока команда работает, поиск может отдавать неполные результаты (секунды —
минуты в зависимости от объёма).

## Эксплуатация

- **Однонодовый режим — сознательно**: объёмы музыкальной школы (тысячи
  записей) кластера не требуют. Отсюда жёлтый (`yellow`) статус здоровья —
  норма: репликам некуда размещаться, primary-шарды при этом целы.
- **Память**: heap ограничен `ES_JAVA_OPTS=-Xms256m -Xmx512m`, контейнер —
  `mem_limit: 1g` (prod). Этого хватает с большим запасом.
- **Безопасность**: `xpack.security.enabled=false`, порт наружу не
  публикуется — изоляция сетью compose, как у Redis (в dev порт 9200 открыт
  для отладки).
- **Приложение от ES не зависит**: `php` стартует и работает без него,
  поиск автоматически на ILIKE (в логе — `Search engine unavailable…`).

### Диагностика

```bash
curl -s localhost:9200/_cluster/health | jq        # статус (yellow — норма)
curl -s localhost:9200/_cat/indices?v              # индексы и число документов
curl -s 'localhost:9200/clients/_search?q=name:анна' | jq
docker compose exec php bin/console messenger:failed:show   # упавшие индексации
```

Kibana (только dev, http://localhost:5601) — просмотр индексов руками.
