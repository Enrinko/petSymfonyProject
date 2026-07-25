import { afterEach, describe, expect, it } from 'vitest';
import { uiLocale } from '../../../react/utils/locale';

describe('uiLocale', () => {
    afterEach(() => {
        document.documentElement.removeAttribute('lang');
    });

    it('en в <html lang> даёт en-US', () => {
        document.documentElement.lang = 'en';

        expect(uiLocale()).toBe('en-US');
    });

    it('ru даёт ru-RU', () => {
        document.documentElement.lang = 'ru';

        expect(uiLocale()).toBe('ru-RU');
    });

    it('без lang (jsdom-тесты) — ru-RU', () => {
        expect(uiLocale()).toBe('ru-RU');
    });
});
