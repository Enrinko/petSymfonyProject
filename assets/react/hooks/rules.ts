/**
 * Правила клиентской валидации форм — чистые функции.
 *
 * Клиентская валидация — UX-слой, не безопасность: сервер остаётся
 * источником истины, здесь только простые мгновенные проверки.
 * Тексты — через t(): ключ каталога + русский fallback (см. react/i18n.ts).
 *
 * Пустое значение триггерит только required: правила комбинируются
 * (`[required(), email()]`), и каждое отвечает за своё.
 */

import { t } from '../i18n';

// Getters: перевод читается в момент обращения, а не при импорте модуля
export const RULE_MESSAGES = {
    get required(): string {
        return t('frontend.form.required', 'Обязательное поле');
    },
    get email(): string {
        return t('frontend.form.email', 'Некорректный email');
    },
    minLength: (n: number): string => t('frontend.form.min_length', 'Минимум %n% символов', { n }),
    get matches(): string {
        return t('frontend.form.mismatch', 'Пароли не совпадают');
    },
};

export type Rule = (value: string, values: Record<string, string>) => string | null;

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const required = (msg?: string): Rule =>
    (value) => (value.trim() === '' ? (msg ?? RULE_MESSAGES.required) : null);

export const email = (msg?: string): Rule =>
    (value) => (value !== '' && !EMAIL_RE.test(value) ? (msg ?? RULE_MESSAGES.email) : null);

export const minLength = (n: number, msg?: string): Rule =>
    (value) => (value !== '' && value.length < n ? (msg ?? RULE_MESSAGES.minLength(n)) : null);

export const matches = (other: string, msg?: string): Rule =>
    (value, values) => (value !== '' && value !== values[other] ? (msg ?? RULE_MESSAGES.matches) : null);
