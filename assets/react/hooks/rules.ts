/**
 * Правила клиентской валидации форм — чистые функции.
 *
 * Клиентская валидация — UX-слой, не безопасность: сервер остаётся
 * источником истины, здесь только простые мгновенные проверки.
 * Тексты собраны константами — задел под будущую локализацию (i18n).
 *
 * Пустое значение триггерит только required: правила комбинируются
 * (`[required(), email()]`), и каждое отвечает за своё.
 */

export const RULE_MESSAGES = {
    required: 'Обязательное поле',
    email: 'Некорректный email',
    minLength: (n: number) => `Минимум ${n} символов`,
    matches: 'Пароли не совпадают',
} as const;

export type Rule = (value: string, values: Record<string, string>) => string | null;

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const required = (msg: string = RULE_MESSAGES.required): Rule =>
    (value) => (value.trim() === '' ? msg : null);

export const email = (msg: string = RULE_MESSAGES.email): Rule =>
    (value) => (value !== '' && !EMAIL_RE.test(value) ? msg : null);

export const minLength = (n: number, msg: string = RULE_MESSAGES.minLength(n)): Rule =>
    (value) => (value !== '' && value.length < n ? msg : null);

export const matches = (other: string, msg: string = RULE_MESSAGES.matches): Rule =>
    (value, values) => (value !== '' && value !== values[other] ? msg : null);
