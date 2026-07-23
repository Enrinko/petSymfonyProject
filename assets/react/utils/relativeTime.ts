const MINUTE = 60_000;
const HOUR = 3_600_000;
const DAY = 86_400_000;
const WEEK = 7 * DAY;

const rtf = new Intl.RelativeTimeFormat('ru', { numeric: 'auto' });

/**
 * «Только что», «5 минут назад», «вчера»… — до недели;
 * старше — абсолютная дата, чтобы лента оставалась точной.
 */
export function formatRelative(iso: string, now: Date = new Date()): string {
    const then = new Date(iso);
    const diff = now.getTime() - then.getTime();

    if (diff < MINUTE) {
        return 'только что';
    }

    if (diff < HOUR) {
        return rtf.format(-Math.floor(diff / MINUTE), 'minute');
    }

    if (diff < DAY) {
        return rtf.format(-Math.floor(diff / HOUR), 'hour');
    }

    if (diff < WEEK) {
        return rtf.format(-Math.floor(diff / DAY), 'day');
    }

    return then.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
}
