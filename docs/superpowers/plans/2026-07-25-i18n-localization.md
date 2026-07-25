# i18n-localization (Локализация RU/EN) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Инфраструктура переводов Symfony (RU базовый, EN второй) + локализация auth-экранов и shell + переключатель языка (профиль для юзера, сессия для гостя).

**Architecture:** `symfony/translation` с YAML-каталогами `translations/messages.{ru,en}.yaml` и `validators.{ru,en}.yaml`. Локаль запроса определяет `LocaleRequestListener` (kernel.request, priority 6 — после файрвола): `User.locale` → сессия `_locale` → `Accept-Language` → `ru`. Для React — словарь `frontend.*` текущей локали, выгружаемый одним JSON-`<script data-i18n>` в `base.html.twig` (Twig-расширение `frontend_i18n()`), читаемый хелпером `t(key, fallback, params?)` из `assets/react/i18n.ts`.

**Отклонение от спеки (осознанное):** спека предлагала props `i18n` на каждый `react_component`. Выбран один script-тег + `t()`: единый механизм и для компонентов, и для не-компонентных модулей (`httpClient.ts`, `rules.ts`), ноль churn в шаблонах и в 63+ vitest-тестах (fallback = текущая русская строка, тесты остаются зелёными).

**Tech Stack:** symfony/translation, twig/intl-extra, Doctrine migration (User.locale), React 18 + TS, Vitest, PHPUnit.

## Global Constraints

- Все команды — в контейнерах: `docker compose exec php …`, `docker compose run --rm node …` (см. CLAUDE.md).
- PHPStan level 8; ESLint/tsc/Stylelint зелёные; `bin/phpunit` и `npm run test` зелёные.
- Коммиты: subject = имя ветки `i18n-localization/feature/23/enrinko`, body — список изменений, **без** Co-Authored-By/Generated-with футеров (глобальный CLAUDE.md).
- Ветка от `main`: `i18n-localization/feature/23/enrinko`; в конце — локальный merge в `main` (как у прошлых фич).
- Ключи переводов: `layout.*` (shell/auth-layout), `page.{login,register,forgot,reset,verify}.*`, `auth.*` (API-сообщения аутентификации), `frontend.*` (словарь React); валидаторы — домен `validators`, ключи `auth.*`.
- RU-строки в каталоге ru == текущие строки интерфейса byte-to-byte (иначе поломаются functional-тесты, которые их assert'ят).
- Новые user-facing строки — только через ключи (закрепляется в `docs/ru-дизайн-система.md`).

## Out of scope (зафиксировать в спеке как follow-up)

- Письма (`templates/email/*`) — уходят асинхронно через Messenger, воркер не знает локали запроса; нужна передача локали в message — отдельная задача.
- `SearchPalette`, CRM-страницы (clients/events/schedule/admin), `ProfileForm` целиком (кроме новой секции языка) — переводятся при работе над своими фичами.
- Статические конверты `ApiJson::validationError()/invalidJson()` («Данные не прошли валидацию.», «Тело запроса…») — общая API-инфраструктура, не auth; field-ошибки при этом переводятся валидатором автоматически.

---

### Task 1: Инфраструктура переводов (пакеты + конфиг + каталоги + `lang`)

**Files:**
- Modify: `composer.json` (через composer require)
- Create: `config/packages/translation.yaml`
- Create: `translations/messages.ru.yaml`, `translations/messages.en.yaml`, `translations/validators.ru.yaml`, `translations/validators.en.yaml` (болванки, наполняются в задачах 4–6)
- Modify: `templates/base.html.twig:2` (`lang`)

**Interfaces:**
- Produces: работающий `TranslatorInterface`, `|trans` в Twig, `app.request.locale` в шаблонах.

- [x] **Step 1: Установить пакеты**

```bash
docker compose up --wait
docker compose exec php composer require symfony/translation twig/intl-extra
```
Expected: recipe создаёт `translations/` и `config/packages/translation.yaml`.

- [x] **Step 2: Конфиг переводчика**

`config/packages/translation.yaml`:
```yaml
framework:
    default_locale: ru
    enabled_locales: ['ru', 'en']
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks: ['ru']
```

- [x] **Step 3: `lang` из локали запроса**

`templates/base.html.twig`: `<html lang="ru">` → `<html lang="{{ app.request.locale }}">`.

- [x] **Step 4: Каталоги-болванки** — создать 4 файла с корневым комментарием о неймспейсах (содержимое наполняют задачи 4–6).

- [x] **Step 5: Проверка** — `docker compose exec php bin/phpunit` зелёный, `docker compose exec php bin/console debug:config framework translator` показывает fallbacks ru.

- [x] **Step 6: Commit** `i18n-localization/feature/23/enrinko` (body: translation infrastructure).

---

### Task 2: `User.locale` + миграция + резолвер локали + kernel-листенер

**Files:**
- Modify: `src/Domain/User/User.php` (поле `locale`, `getLocale()`, `changeLocale()`)
- Create: `migrations/VersionXXXX.php` (`make:migration`)
- Create: `src/Infrastructure/Http/LocaleResolver.php`
- Create: `src/Infrastructure/Http/LocaleRequestListener.php`
- Test: `tests/Unit/Infrastructure/Http/LocaleResolverTest.php`

**Interfaces:**
- Produces: `LocaleResolver::resolve(Request $request): string` (возвращает `'ru'|'en'`); `User::getLocale(): ?string`; `User::changeLocale(?string $locale): void` (валидирует по списку, бросает `\InvalidArgumentException` на мусор); константа `LocaleResolver::SUPPORTED = ['ru', 'en']`, `LocaleResolver::SESSION_KEY = '_locale'`.
- Consumes: `TokenStorageInterface` (юзер), `Request` (сессия + Accept-Language).

- [x] **Step 1: Поле в User** (после `$avatarPath`):

```php
    /** Предпочитаемая локаль интерфейса; null — определяем по сессии/браузеру */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $locale = null;

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function changeLocale(?string $locale): void
    {
        if ($locale !== null && !\in_array($locale, ['ru', 'en'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported locale "%s".', $locale));
        }
        $this->locale = $locale;
    }
```

- [x] **Step 2: Миграция** — `docker compose exec php bin/console make:migration`, затем `doctrine:migrations:migrate -n` и `doctrine:schema:validate`.

- [x] **Step 3: Failing test** `tests/Unit/Infrastructure/Http/LocaleResolverTest.php` (фейки руками по конвенции `tests/Fake/`): случаи — юзер с locale=en → en; юзер без locale + сессия en → en; гость + сессия → сессия; гость без сессии + Accept-Language en → en; ничего → ru; мусор в сессии → игнор.

- [x] **Step 4: Реализация**

`src/Infrastructure/Http/LocaleResolver.php`:
```php
final readonly class LocaleResolver
{
    public const array SUPPORTED = ['ru', 'en'];
    public const string SESSION_KEY = '_locale';

    public function __construct(private TokenStorageInterface $tokenStorage) {}

    public function resolve(Request $request): string
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof User && \in_array($user->getLocale(), self::SUPPORTED, true)) {
            return $user->getLocale();
        }
        if ($request->hasPreviousSession()) {
            $sessionLocale = $request->getSession()->get(self::SESSION_KEY);
            if (\in_array($sessionLocale, self::SUPPORTED, true)) {
                return $sessionLocale;
            }
        }
        return $request->getPreferredLanguage(self::SUPPORTED) ?? 'ru';
    }
}
```

`src/Infrastructure/Http/LocaleRequestListener.php` — по образцу `ApiCsrfProtectionListener` (`#[AsEventListener]`):
```php
/**
 * Локаль: параметр пользователя → сессия → Accept-Language → ru.
 * Priority 6 — после файрвола (8), чтобы видеть аутентифицированного юзера.
 * LocaleSwitcher — чтобы переводчик и прочие LocaleAware-сервисы
 * получили локаль независимо от штатного LocaleAwareListener (он выше по приоритету).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class LocaleRequestListener
{
    public function __construct(private LocaleResolver $localeResolver, private LocaleSwitcher $localeSwitcher) {}

    public function __invoke(RequestEvent $event): void
    {
        $locale = $this->localeResolver->resolve($event->getRequest());
        $event->getRequest()->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }
}
```

- [x] **Step 5: Тесты + PHPStan зелёные**
- [x] **Step 6: Commit**

---

### Task 3: `POST /locale` + переключатель гостя на auth-layout

**Files:**
- Create: `src/Controller/LocaleController.php`
- Modify: `templates/layout/auth.html.twig` (кнопка RU/EN в `.auth__toggles`)
- Test: `tests/Functional/LocaleSwitchTest.php`

**Interfaces:**
- Produces: `POST /locale` (form-data `_locale=ru|en`, `_token` CSRF `switch_locale`) → 303 redirect на Referer (same-origin, иначе `/`); пишет сессию всегда, `User.locale` — если аутентифицирован.

- [x] **Step 1: Failing functional test** — гость: POST /locale en + CSRF → редирект, страница `/login` отвечает `<html lang="en">`; юзер: POST → `User.locale` обновлён в БД; invalid `_locale` → 400.

- [x] **Step 2: Контроллер**

```php
final class LocaleController extends AbstractController
{
    #[Route('/locale', name: 'app_locale_switch', methods: ['POST'])]
    public function __invoke(Request $request, UserRepositoryInterface $users): Response
    {
        if (!$this->isCsrfTokenValid('switch_locale', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $locale = (string) $request->request->get('_locale');
        if (!\in_array($locale, LocaleResolver::SUPPORTED, true)) {
            throw new BadRequestHttpException('Unsupported locale.');
        }
        $request->getSession()->set(LocaleResolver::SESSION_KEY, $locale);
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->changeLocale($locale);
            $users->save($user);
        }
        $referer = $request->headers->get('referer');
        $target = \is_string($referer) && str_starts_with($referer, $request->getSchemeAndHttpHost()) ? $referer : '/';
        return $this->redirect($target, Response::HTTP_SEE_OTHER);
    }
}
```

- [x] **Step 3: Переключатель гостя** в `layout/auth.html.twig` рядом с toggle-кнопками — мини-форма (кнопка показывает целевой язык):

```twig
<form class="locale-switch" method="post" action="{{ path('app_locale_switch') }}">
    <input type="hidden" name="_token" value="{{ csrf_token('switch_locale') }}">
    <input type="hidden" name="_locale" value="{{ app.request.locale == 'ru' ? 'en' : 'ru' }}">
    <button type="submit" class="theme-toggle locale-switch__btn"
            aria-label="{{ 'layout.locale.switch'|trans }}" title="{{ 'layout.locale.switch'|trans }}">
        {{ app.request.locale == 'ru' ? 'EN' : 'RU' }}
    </button>
</form>
```
(+ минимальный SCSS для `.locale-switch`, если существующие классы не покрывают.)

- [x] **Step 4: Тесты зелёные; Commit**

---

### Task 4: Auth-шаблоны и shell на `|trans` + каталоги `layout.*` / `page.*`

**Files:**
- Modify: `templates/layout/auth.html.twig`, `templates/layout/shell.html.twig`, `templates/security/{login,register,forgot_password,reset_password,verify_email_result}.html.twig`, `templates/profile/index.html.twig` (title/hint)
- Modify: `translations/messages.ru.yaml`, `translations/messages.en.yaml`

Каждая русская строка шаблона → ключ; RU-каталог хранит текущие строки как есть. Ключевые EN-переводы (музыкальные метафоры сохраняются): Увертюра→Overture, Первая нота→First note, Реприза→Reprise, Финал→Finale, Кода→Coda, Пауза→Rest; Рабочий стол→Dashboard, Ученики→Students, Расписание→Schedule, Мероприятия→Events, Оркестр→Orchestra, Проекты→Projects, Администрирование→Administration, Пользователи и роли→Users & roles, Журнал безопасности→Security log, Инструменты→Instruments, Стайлгайд→Styleguide, Выйти ↗→Sign out ↗, Профиль→Profile, Поиск→Search.

Строки с разметкой (`auth__pitch-title` с `<em>`) — ключ + `|trans|raw` (перевод статичен, не user input). Титулы страниц: `{% block title %}{{ 'page.login.title'|trans }}{% endblock %}` (в RU — «Вход — petSymphony CRM» и т.д.).

- [x] **Step 1:** Наполнить `messages.ru.yaml` всеми ключами (значения — текущие строки byte-to-byte).
- [x] **Step 2:** `messages.en.yaml` — переводы.
- [x] **Step 3:** Переписать 8 шаблонов на `|trans`.
- [x] **Step 4:** Проверка: `bin/phpunit` (functional-тесты, assert'ящие русские тексты, остаются зелёными — RU дефолт), `bin/console lint:twig templates/`, ручной smoke `curl -k https://localhost/login` (RU) и с `Accept-Language: en` (EN).
- [x] **Step 5: Commit**

---

### Task 5: API-сообщения auth и валидаторы на ключи

**Files:**
- Modify: `src/Application/User/RegisterUserCommand.php`, `src/Application/PasswordReset/{RequestPasswordResetCommand,ResetPasswordCommand}.php` — `message:` → ключи `validators`-домена (`auth.email.blank`, `auth.email.invalid`, `auth.email.too_long`, `auth.password.blank`, `auth.password.too_short`, `auth.password.weak`, `auth.password.compromised`, `auth.password.mismatch`, `auth.reset.token_blank`); `{{ limit }}` плейсхолдеры сохраняются.
- Modify: `src/Controller/Api/RegistrationController.php` («Этот email уже зарегистрирован.» → `auth.register.email_taken`, «Аккаунт создан.» → `auth.register.created`), `src/Controller/SecurityController.php` (apiLogin 401-сообщение → `auth.login.json_required`), `src/Controller/Api/PasswordResetController.php` (3 сообщения → `auth.reset.*`).
- Modify: `src/Infrastructure/Security/AppAuthenticationFailureHandler.php` (строки → `auth.login.{throttled,failed}` + `AccountStatusException` → `trans($e->getMessageKey(), $e->getMessageData())`), `AppAuthenticationEntryPoint.php` (`auth.required`), `TwoFactorJsonHandlers.php` (строки → `auth.2fa.*`), `ActiveUserChecker.php` (сообщение исключения → ключ `auth.account.deactivated`).
- Modify: `translations/validators.{ru,en}.yaml`, `translations/messages.{ru,en}.yaml` (`auth.*`).

**Interfaces:**
- Consumes: `LocaleResolver::resolve()` — в security-хендлерах (они срабатывают в файрволе ДО `LocaleRequestListener`, поэтому локаль резолвится явно): `$this->translator->trans($key, [], null, $this->localeResolver->resolve($request))`.
- Контроллеры (после листенера) — просто `TranslatorInterface::trans($key)`.

- [x] **Step 1:** validators-ключи + каталоги (RU значения = текущие строки).
- [x] **Step 2:** Контроллеры на `TranslatorInterface`.
- [x] **Step 3:** Security-хендлеры на translator + `LocaleResolver`.
- [x] **Step 4:** `bin/phpunit` (JsonLoginTest/RegistrationFlowTest/PasswordResetFlowTest и юнит-тесты хендлеров — зелёные, т.к. RU-строки не изменились; если у юнит-тестов хендлеров нет фейка translator — добавить простой фейк в `tests/Fake/`), PHPStan.
- [x] **Step 5: Commit**

---

### Task 6: React — `t()` + словарь `frontend.*` + auth-формы

**Files:**
- Create: `src/Infrastructure/Twig/FrontendI18nExtension.php` (Twig-функция `frontend_i18n(): array` — все ключи `frontend.*` из каталога текущей локали, с fallback-merge ru)
- Modify: `templates/base.html.twig` — перед `</head>`: `<script type="application/json" data-i18n>{{ frontend_i18n()|json_encode(constant('JSON_HEX_TAG') b-or constant('JSON_HEX_AMP') b-or constant('JSON_HEX_APOS') b-or constant('JSON_HEX_QUOT'))|raw }}</script>`
- Create: `assets/react/i18n.ts`:
```ts
let cache: Record<string, string> | null = null;
function dict(): Record<string, string> {
    if (cache === null) {
        const el = document.querySelector('script[data-i18n]');
        try { cache = el?.textContent ? (JSON.parse(el.textContent) as Record<string, string>) : {}; }
        catch { cache = {}; }
    }
    return cache;
}
/** Перевод по ключу; fallback — русская строка (jsdom-тесты работают без словаря). */
export function t(key: string, fallback: string, params?: Record<string, string | number>): string {
    let text = dict()[key] ?? fallback;
    for (const [name, value] of Object.entries(params ?? {})) {
        text = text.replaceAll(`%${name}%`, String(value));
    }
    return text;
}
export function resetI18nCache(): void { cache = null; }
```
- Modify: `assets/react/controllers/{LoginForm,RegisterForm,ForgotPasswordForm,ResetPasswordForm}.tsx` — все строки → `t('frontend.auth…', '<текущая русская строка>')`
- Modify: `assets/react/hooks/rules.ts` (`RULE_MESSAGES` → `t()` в момент вызова правила, не на импорте модуля), `assets/react/services/httpClient.ts` (`NETWORK_ERROR_MESSAGE` и таймаут-сообщение → `t('frontend.common.…', …)` в момент ошибки, НЕ на импорте — словарь появляется в DOM до бандла, но лениво надёжнее)
- Modify: `translations/messages.{ru,en}.yaml` (`frontend.*`)
- Test: `assets/tests/react/i18n.test.ts` (lookup из script-тега, fallback без тега, params-интерполяция, resetI18nCache)

- [x] **Step 1: Failing vitest** для `t()`.
- [x] **Step 2:** `i18n.ts` + тест зелёный.
- [x] **Step 3:** Twig-расширение + script-тег; PHPUnit unit-тест расширения (каталог с `frontend.a` и «прочим» ключом → отдаёт только frontend-срез; en-локаль без ключа → ru-значение).
- [x] **Step 4:** Перевести 4 формы + rules + httpClient; наполнить `frontend.*` в каталогах.
- [x] **Step 5:** `npm run test`, `npm run lint`, `npm run typecheck` зелёные (существующие тесты форм — без изменений).
- [x] **Step 6: Commit**

---

### Task 7: Переключатель языка в профиле (`PATCH /api/profile` + UI-секция)

**Files:**
- Modify: `src/Application/Profile/UpdateProfileCommand.php` (+ `public ?string $locale` с `#[Assert\Choice(choices: ['ru', 'en'], message: 'profile.locale.invalid')]`), `UpdateProfileHandler.php` (+ применение locale)
- Modify: `src/Controller/Api/ProfileController.php` — `update()` читает `locale` (точечно: менять только если ключ прислан в payload, чтобы PATCH одного поля не затирал другое), `profilePayload()` + `'locale' => $user->getLocale()`
- Modify: `assets/react/controllers/ProfileForm.tsx` — секция «Язык интерфейса»: кнопки RU/EN, PATCH `{ locale }`, затем `window.location.reload()` (новые строки — сразу через `t()`)
- Modify: сервис профиля в `assets/react/services/` (тип Profile + PATCH payload)
- Test: дополнение `tests/Functional/ProfileApiTest.php` (PATCH locale=en → payload.locale=en; PATCH locale=xx → 422), vitest-тест секции при наличии тестов ProfileForm

- [x] **Step 1:** Failing functional-тест PATCH locale.
- [x] **Step 2:** Command/Handler/Controller + ключ `profile.locale.invalid` в `validators.{ru,en}.yaml`.
- [x] **Step 3:** UI-секция + reload; vitest при наличии тестов ProfileForm.
- [x] **Step 4:** Тесты зелёные; Commit.

---

### Task 8: Даты по локали на фронте

**Files:**
- Create: `assets/react/utils/locale.ts`:
```ts
/** BCP-47 локаль дат из <html lang> (SSOT — LocaleRequestListener). */
export function uiLocale(): string {
    return document.documentElement.lang === 'en' ? 'en-US' : 'ru-RU';
}
```
- Modify (механически `'ru-RU'` → `uiLocale()`): `assets/react/utils/week.ts:31`, `assets/react/utils/relativeTime.ts:32`, `assets/react/controllers/ProfileForm.tsx:283`, `events/EventList.tsx:19`, `events/EventCard.tsx:21`, `admin/UserRoleManager.tsx:22`, `admin/AuditLog.tsx:22`, `clients/ClientCard.tsx:26`, `clients/ClientList.tsx:23`
- Test: `assets/tests/react/utils/locale.test.ts` (lang=en → en-US; пусто → ru-RU)

- [x] **Step 1:** Тест + реализация + замены; существующие тесты дат зелёные (jsdom lang='' → ru-RU).
- [x] **Step 2:** `npm run test && npm run typecheck`; Commit.

---

### Task 9: Документация, статусы, финальная проверка, merge

**Files:**
- Modify: `docs/ru-дизайн-система.md` — раздел «Локализация»: правило «новые строки только через ключи», неймспейсы, `t()` с русским fallback, как добавить язык.
- Modify: `CLAUDE.md` — краткое упоминание i18n-инфраструктуры (каталоги, резолвер, script-тег `frontend.*`, конвенция ключей).
- Modify: `projectDoc/IDEAS/i18n-localization/i18n-localization.md` — статус «реализовано» + список out-of-scope follow-ups; `projectDoc/IDEAS/Backlog.md:32` — статус. (Отдельный вложенный git-репозиторий! `cd` туда персистится — после коммита вернуться в корень: memory `project_nested_repo_cd_trap`.)

- [x] **Step 1:** Полный прогон: `bin/phpunit`, PHPStan, `composer audit`, `npm run lint`, `npm run typecheck`, `npm run stylelint`, `npm run test`, `lint:twig`, `doctrine:schema:validate`.
- [x] **Step 2:** Ручной smoke: `curl /login` RU и с `Accept-Language: en` → EN; `POST /locale` → 303 + липкая сессия.
- [x] **Step 3:** Доки + статусы; коммиты (petSymphony и вложенный projectDoc).
- [x] **Step 4:** Локальный merge в `main` (`git merge --no-ff i18n-localization/feature/23/enrinko`). Push — только по просьбе пользователя.
