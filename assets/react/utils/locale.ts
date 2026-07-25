/**
 * BCP-47-локаль для форматирования дат/времени.
 *
 * Источник — <html lang>, который base.html.twig берёт из локали запроса
 * (LocaleRequestListener): единая точка истины, без пробрасывания пропсов.
 * jsdom-тесты без lang получают ru-RU — прежнее поведение.
 */
export function uiLocale(): 'ru-RU' | 'en-US' {
    return document.documentElement.lang === 'en' ? 'en-US' : 'ru-RU';
}
