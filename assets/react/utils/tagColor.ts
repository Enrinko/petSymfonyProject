export const TAG_COLOR_COUNT = 8;

/**
 * Детерминированный индекс цвета чипа по имени тега (djb2 % 8).
 * Цвет не хранится в БД — одинаковое имя всегда даёт одинаковый цвет.
 */
export function tagColorIndex(name: string): number {
    let hash = 5381;

    for (let i = 0; i < name.length; i++) {
        hash = (hash * 33) ^ name.charCodeAt(i);
    }

    return Math.abs(hash) % TAG_COLOR_COUNT;
}
