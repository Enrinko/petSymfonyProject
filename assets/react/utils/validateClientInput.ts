export interface ClientFormValues {
    name: string;
    email: string;
    phone: string;
    comment: string;
}

import { phoneDigitCount } from './phoneMask';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const MIN_PHONE_DIGITS = 5;
const RU_PHONE_DIGITS = 11;

/**
 * Быстрая проверка формы ученика до запроса. UX-слой, не безопасность:
 * сервер валидирует те же правила и остаётся источником истины.
 */
export function validateClientInput(values: ClientFormValues): Record<string, string> {
    const errors: Record<string, string> = {};

    if (values.name.trim().length < 2) {
        errors.name = 'Укажите имя (минимум 2 символа).';
    }

    const email = values.email.trim();

    if (email !== '' && !EMAIL_PATTERN.test(email)) {
        errors.email = 'Некорректный email.';
    }

    const phone = values.phone.trim();

    if (phone !== '') {
        const digits = phoneDigitCount(phone);
        const isRu = phone.startsWith('+7');

        if (isRu && digits !== RU_PHONE_DIGITS) {
            errors.phone = 'Введите номер полностью: +7 (900) 123-45-67.';
        } else if (!isRu && digits < MIN_PHONE_DIGITS) {
            errors.phone = 'Введите номер полностью.';
        }
    }

    return errors;
}
