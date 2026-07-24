import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { email, matches, minLength, required, RULE_MESSAGES } from '../../../react/hooks/rules';
import { useForm } from '../../../react/hooks/useForm';
import { ApiError } from '../../../react/services/httpClient';

const changeEvent = (value: string) =>
    ({ currentTarget: { value } }) as unknown as React.FormEvent<HTMLInputElement>;

const submitEvent = () =>
    ({ preventDefault: vi.fn() }) as unknown as React.FormEvent<HTMLFormElement>;

const options = (onSubmit = vi.fn().mockResolvedValue(undefined)) => ({
    initial: { email: '', password: '', passwordConfirm: '' },
    rules: {
        email: [required(), email()],
        password: [required(), minLength(10)],
        passwordConfirm: [required(), matches('password')],
    },
    fallbackError: 'Всё сломалось.',
    onSubmit,
});

describe('useForm', () => {
    it('ошибка поля видна после blur, но не при вводе', () => {
        const { result } = renderHook(() => useForm(options()));

        act(() => result.current.fieldProps('email').onChange(changeEvent('not-an-email')));
        expect(result.current.fieldProps('email').error).toBeUndefined();

        act(() => result.current.fieldProps('email').onBlur());
        expect(result.current.fieldProps('email').error).toBe(RULE_MESSAGES.email);
    });

    it('невалидный сабмит показывает все ошибки и не зовёт onSubmit', async () => {
        const onSubmit = vi.fn();
        const { result } = renderHook(() => useForm(options(onSubmit)));

        await act(() => result.current.handleSubmit(submitEvent()));

        expect(onSubmit).not.toHaveBeenCalled();
        expect(result.current.fieldProps('email').error).toBe(RULE_MESSAGES.required);
        expect(result.current.fieldProps('password').error).toBe(RULE_MESSAGES.required);
    });

    it('валидный сабмит зовёт onSubmit со значениями', async () => {
        const onSubmit = vi.fn().mockResolvedValue(undefined);
        const { result } = renderHook(() => useForm(options(onSubmit)));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));
        await act(() => result.current.handleSubmit(submitEvent()));

        expect(onSubmit).toHaveBeenCalledWith({
            email: 'a@b.c',
            password: 'long-password-1',
            passwordConfirm: 'long-password-1',
        });
    });

    it('серверные ошибки полей мержатся в те же errors', async () => {
        const onSubmit = vi.fn().mockRejectedValue(new ApiError(409, 'Conflict', { email: 'Уже занят' }));
        const { result } = renderHook(() => useForm(options(onSubmit)));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));
        await act(() => result.current.handleSubmit(submitEvent()));

        expect(result.current.fieldProps('email').error).toBe('Уже занят');
        expect(result.current.submitting).toBe(false);
    });

    it('ApiError без errors → general = message', async () => {
        const onSubmit = vi.fn().mockRejectedValue(new ApiError(429, 'Слишком часто'));
        const { result } = renderHook(() => useForm(options(onSubmit)));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));
        await act(() => result.current.handleSubmit(submitEvent()));

        expect(result.current.errors.general).toBe('Слишком часто');
    });

    it('не-ApiError → general = fallbackError', async () => {
        const onSubmit = vi.fn().mockRejectedValue(new TypeError('boom'));
        const { result } = renderHook(() => useForm(options(onSubmit)));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));
        await act(() => result.current.handleSubmit(submitEvent()));

        expect(result.current.errors.general).toBe('Всё сломалось.');
    });

    it('matches перевалидируется при изменении первого пароля', async () => {
        const { result } = renderHook(() => useForm(options()));

        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onBlur());
        expect(result.current.fieldProps('passwordConfirm').error).toBeUndefined();

        // Меняем ПЕРВЫЙ пароль — ошибка должна появиться у второго (он touched)
        act(() => result.current.fieldProps('password').onChange(changeEvent('another-password-2')));
        expect(result.current.fieldProps('passwordConfirm').error).toBe(RULE_MESSAGES.matches);
    });

    it('submitting=true, пока onSubmit в полёте', async () => {
        let release!: () => void;
        const gate = new Promise<void>((resolve) => { release = resolve; });
        const onSubmit = vi.fn().mockReturnValue(gate);
        const { result } = renderHook(() => useForm(options(onSubmit)));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        act(() => result.current.fieldProps('password').onChange(changeEvent('long-password-1')));
        act(() => result.current.fieldProps('passwordConfirm').onChange(changeEvent('long-password-1')));

        let pending!: Promise<void>;
        act(() => { pending = result.current.handleSubmit(submitEvent()); });
        expect(result.current.submitting).toBe(true);

        release();
        await act(() => pending);
        expect(result.current.submitting).toBe(false);
    });

    it('validate (формо-уровневый) мержится после пополевых правил', async () => {
        const onSubmit = vi.fn();
        const { result } = renderHook(() => useForm({
            initial: { name: '', phone: '' },
            validate: (values): Record<string, string> =>
                (values.phone === '123' ? { phone: 'Введите номер полностью.' } : {}),
            onSubmit,
        }));

        act(() => result.current.fieldProps('phone').onChange(changeEvent('123')));
        await act(() => result.current.handleSubmit(submitEvent()));

        expect(onSubmit).not.toHaveBeenCalled();
        expect(result.current.fieldProps('phone').error).toBe('Введите номер полностью.');
    });

    it('setAll заполняет все значения и сбрасывает touched/ошибки', () => {
        const { result } = renderHook(() => useForm(options()));

        act(() => result.current.fieldProps('email').onBlur());
        act(() => result.current.setAll({ email: 'a@b.c', password: 'long-password-1', passwordConfirm: 'long-password-1' }));

        expect(result.current.values.email).toBe('a@b.c');
        expect(result.current.fieldProps('email').error).toBeUndefined();
    });

    it('reset возвращает к initial и чистит ошибки', async () => {
        const { result } = renderHook(() => useForm(options()));

        act(() => result.current.fieldProps('email').onChange(changeEvent('a@b.c')));
        await act(() => result.current.handleSubmit(submitEvent()));
        act(() => result.current.reset());

        expect(result.current.values.email).toBe('');
        expect(result.current.errors).toEqual({});
        expect(result.current.fieldProps('password').error).toBeUndefined();
    });
});
