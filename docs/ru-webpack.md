# Webpack Encore — сборка frontend-ресурсов

## Что такое Webpack Encore

Webpack Encore — это обёртка Symfony над Webpack, которая упрощает настройку сборки JS/TS/CSS. Конфигурация находится в `webpack.config.js`.

## Конфигурация (webpack.config.js)

```javascript
Encore
    .setOutputPath('public/build/')      // Куда складывать собранные файлы
    .setPublicPath('/build')             // URL-путь к ресурсам
    .addEntry('app', './assets/app.ts')  // Точка входа

    .splitEntryChunks()                  // Разбивка на чанки для оптимизации
    .enableReactPreset()                 // Поддержка JSX/React
    .enableStimulusBridge('./assets/controllers.json')
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()          // Очищать public/build/ перед сборкой
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())  // Хэши в именах файлов для продакшена
    .configureBabelPresetEnv(config => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
    .enableTypeScriptLoader();           // Поддержка TypeScript
```

## Команды сборки

### Через Docker (рекомендуется)

В dev-окружении контейнер `node` запускается автоматически вместе с `docker compose up` и следит за изменениями в `assets/`:

```bash
# Посмотреть логи компиляции assets
docker compose logs -f node

# Разовая сборка (dev)
docker compose run --rm node npm run dev

# Оптимизированная сборка (prod)
docker compose run --rm node npm run build
```

### Локально (если Node.js установлен на хосте)

```bash
# Разработка — разовая сборка
npm run dev

# Разработка — следить за изменениями
npm run watch

# Dev-сервер с горячей перезагрузкой (HMR)
npm run dev-server

# Продакшен (минификация, хэши, оптимизация)
npm run build
```

## Структура assets/

```
assets/
├── app.ts                          # Точка входа (imports всего)
├── stimulus_bootstrap.js           # Инициализация Stimulus
├── controllers.json                # Конфиг Symfony UX
├── react/
│   ├── controllers/                # React-компоненты (автоматически регистрируются)
│   │   ├── DepositForm.tsx
│   │   └── Hello.jsx
│   └── services/
│       └── ApiService.ts           # HTTP-клиент
└── styles/
    └── app.css                     # Глобальные стили
```

## Как Twig подключает ресурсы

```twig
{# В шаблоне base.html.twig #}
{{ encore_entry_link_tags('app') }}    {# CSS #}
{{ encore_entry_script_tags('app') }} {# JS #}
```

Webpack создаёт `public/build/manifest.json` с маппингом имён файлов на хэшированные версии. Twig-функции читают этот манифест и подставляют правильные URL.

## Добавление SCSS/Sass

```javascript
// webpack.config.js
.enableSassLoader()
```

```bash
npm install sass-loader sass --save-dev
```

## Добавление нового entry point

Если нужна отдельная страница с другим набором скриптов/стилей:

```javascript
// webpack.config.js
.addEntry('admin', './assets/admin.ts')
```

```twig
{# В шаблоне admin/layout.html.twig #}
{{ encore_entry_link_tags('admin') }}
{{ encore_entry_script_tags('admin') }}
```