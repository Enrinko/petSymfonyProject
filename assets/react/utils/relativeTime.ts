import { t } from '../i18n';
import { uiLocale } from './locale';

const MINUTE = 60_000;
const HOUR = 3_600_000;
const DAY = 86_400_000;
const WEEK = 7 * DAY;

// Лениво: локаль известна только после загрузки документа (html lang)
let rtf: Intl.RelativeTimeFormat | null = null;

function relativeFormatter(): Intl.RelativeTimeFormat {
    rtf ??= new Intl.RelativeTimeFormat(uiLocale() === 'en-US' ? 'en' : 'ru', { numeric: 'auto' });

    return rtf;
}

/**
 * «Только что», «5 минут назад», «вчера»… — до недели;
 * старше — абсолютная дата, чтобы лента оставалась точной.
 */
export function formatRelative(iso: string, now: Date = new Date()): string {
    const then = new Date(iso);
    const diff = now.getTime() - then.getTime();

    if (diff < MINUTE) {
        return t('frontend.common.just_now', 'только что');
    }

    if (diff < HOUR) {
        return relativeFormatter().format(-Math.floor(diff / MINUTE), 'minute');
    }

    if (diff < DAY) {
        return relativeFormatter().format(-Math.floor(diff / HOUR), 'hour');
    }

    if (diff < WEEK) {
        return relativeFormatter().format(-Math.floor(diff / DAY), 'day');
    }

    return then.toLocaleDateString(uiLocale(), { day: 'numeric', month: 'short', year: 'numeric' });
}
