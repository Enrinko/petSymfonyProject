import { uiLocale } from './locale';

export const DAY_NAMES = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

/** Понедельник недели, содержащей дату (в локальном времени). */
export function mondayOf(date: Date): Date {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const weekday = (d.getDay() + 6) % 7; // 0 = понедельник
    d.setDate(d.getDate() - weekday);
    return d;
}

export function addDays(date: Date, days: number): Date {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
}

/** YYYY-MM-DD в локальном времени (без сдвига UTC, как toISOString). */
export function toDateParam(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function formatDayLabel(date: Date): string {
    return `${date.getDate()}.${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(uiLocale(), { hour: '2-digit', minute: '2-digit' });
}

export function isSameDay(iso: string, date: Date): boolean {
    const d = new Date(iso);
    return d.getFullYear() === date.getFullYear()
        && d.getMonth() === date.getMonth()
        && d.getDate() === date.getDate();
}
