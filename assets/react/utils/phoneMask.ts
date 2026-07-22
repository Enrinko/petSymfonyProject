/**
 * Лёгкая маска телефона без библиотек.
 *
 * Правила:
 * - в поле остаются только цифры и ведущий «+»;
 * - российские номера приводятся к «+7 (900) 123-45-67»:
 *   ввод с «8», «7», «+7» или сразу с мобильного «9…» считается российским;
 * - прочие международные («+49…») остаются как «+» и цифры без украшений;
 * - длина ограничена 15 цифрами (E.164).
 */

const MAX_DIGITS = 15;
const RU_DIGITS = 11;

function formatRu(digits: string): string {
    const d = digits.slice(0, RU_DIGITS);
    let out = '+7';

    if (d.length > 1) {
        out += ' (' + d.slice(1, 4);
    }

    if (d.length >= 4) {
        out += ')';
    }

    if (d.length > 4) {
        out += ' ' + d.slice(4, 7);
    }

    if (d.length > 7) {
        out += '-' + d.slice(7, 9);
    }

    if (d.length > 9) {
        out += '-' + d.slice(9, 11);
    }

    return out;
}

export function formatPhoneInput(raw: string): string {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return '';
    }

    const hasPlus = trimmed.startsWith('+');
    let digits = trimmed.replace(/\D/g, '');

    if (digits === '') {
        return hasPlus ? '+' : '';
    }

    // «8 900…» — российская запись, приводим к +7
    if (!hasPlus && digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }

    // «900…» без префикса — российский мобильный, дописываем 7
    if (!hasPlus && digits.startsWith('9') && digits.length <= RU_DIGITS - 1) {
        digits = '7' + digits;
    }

    if (digits.startsWith('7')) {
        return formatRu(digits);
    }

    return '+' + digits.slice(0, MAX_DIGITS);
}

/** Сколько цифр набрано (для проверки полноты номера). */
export function phoneDigitCount(value: string): number {
    return value.replace(/\D/g, '').length;
}
