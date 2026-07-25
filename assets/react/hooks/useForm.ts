import { FormEvent, useCallback, useState } from 'react';
import { ApiError } from '../services/httpClient';
import { Rule } from './rules';

/**
 * Лёгкий каркас формы: значения, ошибки по полям, touched, submitting.
 *
 * Политика показа ошибок — «не кричать при вводе»: ошибка поля видна
 * только после его blur или после первого сабмита. Правила гоняются
 * на каждом изменении по всему набору (дёшево при ≤6 полях) — так
 * matches('password') реагирует на изменение ОБОИХ участников.
 *
 * Серверные ошибки (конверт {message, errors} из ApiError) мержатся
 * в те же errors: у пользователя один вид ошибок, откуда бы они ни шли.
 * Ключ 'general' — не-полевая ошибка, формы рендерят её в Alert.
 */
interface UseFormOptions<T extends Record<string, string>> {
    initial: T;
    rules?: Partial<Record<keyof T, Rule[]>>;
    /**
     * Формо-уровневый валидатор (например, validateClientInput) — для
     * доменных проверок, не раскладывающихся на пополевые правила.
     * Его ошибки мержатся ПОВЕРХ пополевых.
     */
    validate?: (values: T) => Record<string, string>;
    onSubmit: (values: T) => Promise<void>;
    /** Текст general-ошибки при не-ApiError (у каждой формы свой). */
    fallbackError?: string;
}

interface FieldProps {
    value: string;
    error: string | undefined;
    onChange: (e: FormEvent<HTMLInputElement>) => void;
    onBlur: () => void;
}

const DEFAULT_FALLBACK = 'Не удалось выполнить запрос.';

export function useForm<T extends Record<string, string>>(options: UseFormOptions<T>) {
    const { initial, rules, validate: validateForm, onSubmit, fallbackError = DEFAULT_FALLBACK } = options;

    const [values, setValues] = useState<T>(initial);
    const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
    const [touched, setTouched] = useState<Record<string, boolean>>({});
    const [submitted, setSubmitted] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const validate = useCallback((current: T): Record<string, string> => {
        const found: Record<string, string> = {};

        for (const name in rules) {
            for (const rule of rules[name] ?? []) {
                const message = rule(current[name], current);
                if (message !== null) {
                    found[name] = message;
                    break; // первое сработавшее правило поля
                }
            }
        }

        return { ...found, ...validateForm?.(current) };
    }, [rules, validateForm]);

    const clientErrors = validate(values);

    // Серверная ошибка поля живёт, пока поле не изменили; клиентская — по правилам
    const errors: Record<string, string> = { ...clientErrors, ...serverErrors };

    const isVisible = (name: string): boolean => submitted || touched[name] === true;

    const setValue = (name: keyof T, value: string) => {
        setValues((prev) => ({ ...prev, [name]: value }));
        // Поле изменили — его серверная ошибка больше не актуальна
        setServerErrors(({ [name as string]: _, ...rest }) => rest);
    };

    const fieldProps = (name: keyof T): FieldProps => ({
        value: values[name],
        error: isVisible(name as string) ? errors[name as string] : undefined,
        onChange: (e) => setValue(name, e.currentTarget.value),
        onBlur: () => setTouched((prev) => ({ ...prev, [name]: true })),
    });

    const handleSubmit = async (e: FormEvent<HTMLFormElement>): Promise<void> => {
        e.preventDefault();
        setSubmitted(true);
        setServerErrors({});

        if (Object.keys(validate(values)).length > 0) {
            return;
        }

        setSubmitting(true);

        try {
            await onSubmit(values);
        } catch (err) {
            if (err instanceof ApiError) {
                setServerErrors(err.errors ?? { general: err.message });
            } else {
                setServerErrors({ general: fallbackError });
            }
        }

        setSubmitting(false);
    };

    const reset = () => {
        setValues(initial);
        setServerErrors({});
        setTouched({});
        setSubmitted(false);
    };

    /** Заполнить форму целиком (вход в режим редактирования) — с чистого листа. */
    const setAll = (next: T) => {
        setValues(next);
        setServerErrors({});
        setTouched({});
        setSubmitted(false);
    };

    return {
        values,
        // Тот же порядок мержа, что и у fieldProps (строка выше): серверная
        // ошибка поля перекрывает клиентскую (и гаснет при правке поля — setValue).
        // Клиентские ошибки — только после сабмита (не «кричим» при вводе).
        /** Все ошибки без фильтра по touched (general — для Alert). */
        errors: { ...(submitted ? clientErrors : {}), ...serverErrors },
        submitting,
        fieldProps,
        setValue,
        setAll,
        handleSubmit,
        reset,
    };
}
