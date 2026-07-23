import { Instrument } from '../../services/InstrumentApiService';
import { instrumentIcon } from '../../utils/instrumentIcon';

interface InstrumentPickerProps {
    label: string;
    catalog: Instrument[];
    selected: number[];
    onChange: (ids: number[]) => void;
}

/**
 * Мультиселект инструментов из справочника: чипы-переключатели,
 * сгруппированные по категории. Создавать инструменты нельзя —
 * только выбирать существующие (справочник ведёт админ).
 */
export default function InstrumentPicker({ label, catalog, selected, onChange }: InstrumentPickerProps) {
    const toggle = (id: number) => {
        onChange(selected.includes(id) ? selected.filter((i) => i !== id) : [...selected, id]);
    };

    if (catalog.length === 0) {
        return null;
    }

    // Группировка по категории с сохранением порядка справочника
    const groups: { label: string; items: Instrument[] }[] = [];
    for (const instrument of catalog) {
        let group = groups.find((g) => g.label === instrument.categoryLabel);
        if (!group) {
            group = { label: instrument.categoryLabel, items: [] };
            groups.push(group);
        }
        group.items.push(instrument);
    }

    return (
        <div className="field instrument-picker">
            <span className="field__label">{label}</span>
            <div className="instrument-picker__groups">
                {groups.map((group) => (
                    <div key={group.label} className="instrument-picker__group">
                        <span className="instrument-picker__group-label">
                            <span aria-hidden="true">{instrumentIcon(group.items[0].category)}</span> {group.label}
                        </span>
                        <div className="instrument-picker__chips">
                            {group.items.map((instrument) => {
                                const active = selected.includes(instrument.id);
                                return (
                                    <button
                                        key={instrument.id}
                                        type="button"
                                        className={`instrument-chip${active ? ' instrument-chip--on' : ''}`}
                                        aria-pressed={active}
                                        onClick={() => toggle(instrument.id)}
                                    >
                                        <span aria-hidden="true">{active ? '✓' : instrumentIcon(instrument.category)}</span>
                                        {' '}{instrument.name}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
