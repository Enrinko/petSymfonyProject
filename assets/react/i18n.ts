/**
 * Словарь фронтенда: base.html.twig выгружает срез frontend.* каталога
 * текущей локали в <script type="application/json" data-i18n> (см.
 * FrontendI18nExtension). Здесь — ленивое чтение с кешем.
 *
 * Fallback-строка (русская) живёт прямо в вызове: jsdom-тесты работают
 * без словаря, а рассинхрон с каталогом не роняет интерфейс.
 */

let cache: Record<string, string> | null = null;

function dict(): Record<string, string> {
    if (cache === null) {
        const el = document.querySelector('script[data-i18n]');

        try {
            cache = el?.textContent ? (JSON.parse(el.textContent) as Record<string, string>) : {};
        } catch {
            cache = {};
        }
    }

    return cache;
}

/** Перевод по ключу; params подставляются в плейсхолдеры вида %name%. */
export function t(key: string, fallback: string, params?: Record<string, string | number>): string {
    let text = dict()[key] ?? fallback;

    for (const [name, value] of Object.entries(params ?? {})) {
        // split/join вместо replaceAll: tsconfig таргетит lib ниже es2021
        text = text.split(`%${name}%`).join(String(value));
    }

    return text;
}

/** Сброс кеша словаря — для тестов. */
export function resetI18nCache(): void {
    cache = null;
}
