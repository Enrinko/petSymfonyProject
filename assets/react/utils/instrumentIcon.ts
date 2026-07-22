// Иконка по категории инструмента. Категории фиксированы enum'ом на бэкенде
// (InstrumentCategory), поэтому маппинг полный; '♪' — запасной вариант.
const CATEGORY_ICONS: Record<string, string> = {
    keyboard: '🎹',
    strings: '🎻',
    winds: '🎷',
    percussion: '🥁',
    vocal: '🎤',
};

export function instrumentIcon(category: string): string {
    return CATEGORY_ICONS[category] ?? '♪';
}
