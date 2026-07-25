import { afterEach, describe, expect, it } from 'vitest';
import { resetI18nCache, t } from '../../react/i18n';

/** Вставляет словарь так же, как base.html.twig: <script type="application/json" data-i18n>. */
function mountDictionary(dict: Record<string, string>): void {
    const script = document.createElement('script');
    script.type = 'application/json';
    script.setAttribute('data-i18n', '');
    script.textContent = JSON.stringify(dict);
    document.head.appendChild(script);
    resetI18nCache();
}

describe('t()', () => {
    afterEach(() => {
        document.querySelectorAll('script[data-i18n]').forEach((el) => el.remove());
        resetI18nCache();
    });

    it('берёт перевод из словаря при наличии ключа', () => {
        mountDictionary({ 'frontend.auth.login.submit': 'Sign in' });

        expect(t('frontend.auth.login.submit', 'Войти')).toBe('Sign in');
    });

    it('возвращает fallback без словаря (jsdom-тесты компонентов)', () => {
        expect(t('frontend.auth.login.submit', 'Войти')).toBe('Войти');
    });

    it('возвращает fallback при отсутствии ключа в словаре', () => {
        mountDictionary({ 'frontend.other': 'x' });

        expect(t('frontend.auth.login.submit', 'Войти')).toBe('Войти');
    });

    it('интерполирует параметры вида %name%', () => {
        mountDictionary({ 'frontend.form.min_length': 'At least %n% characters' });

        expect(t('frontend.form.min_length', 'Минимум %n% символов', { n: 10 })).toBe('At least 10 characters');
        expect(t('frontend.form.missing', 'Минимум %n% символов', { n: 5 })).toBe('Минимум 5 символов');
    });

    it('переживает битый JSON в словаре', () => {
        const script = document.createElement('script');
        script.type = 'application/json';
        script.setAttribute('data-i18n', '');
        script.textContent = '{broken';
        document.head.appendChild(script);
        resetI18nCache();

        expect(t('frontend.any', 'запасной')).toBe('запасной');
    });
});
