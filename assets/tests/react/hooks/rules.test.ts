import { describe, expect, it } from 'vitest';
import { email, matches, minLength, required, RULE_MESSAGES } from '../../../react/hooks/rules';

describe('validation rules', () => {
    it('required: пусто и пробелы — ошибка, значение — null', () => {
        expect(required()('', {})).toBe(RULE_MESSAGES.required);
        expect(required()('   ', {})).toBe(RULE_MESSAGES.required);
        expect(required()('x', {})).toBeNull();
    });

    it('email: невалидный формат — ошибка, валидный — null', () => {
        expect(email()('not-an-email', {})).toBe(RULE_MESSAGES.email);
        expect(email()('a@b', {})).toBe(RULE_MESSAGES.email);
        expect(email()('a@b.c', {})).toBeNull();
    });

    it('email: пустое значение не триггерит (за пустоту отвечает required)', () => {
        expect(email()('', {})).toBeNull();
    });

    it('minLength: короче n — ошибка с числом, иначе null', () => {
        expect(minLength(10)('short', {})).toBe(RULE_MESSAGES.minLength(10));
        expect(minLength(10)('длинный-пароль-123', {})).toBeNull();
        expect(minLength(10)('', {})).toBeNull();
    });

    it('matches: сравнивает с другим полем из values', () => {
        expect(matches('password')('abc', { password: 'xyz' })).toBe(RULE_MESSAGES.matches);
        expect(matches('password')('xyz', { password: 'xyz' })).toBeNull();
        expect(matches('password')('', { password: 'xyz' })).toBeNull();
    });

    it('кастомное сообщение переопределяет дефолт', () => {
        expect(required('Ну надо же заполнить')('', {})).toBe('Ну надо же заполнить');
    });
});
