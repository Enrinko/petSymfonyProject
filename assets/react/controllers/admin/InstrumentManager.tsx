import { useCallback, useEffect, useMemo, useState } from 'react';
import { required } from '../../hooks/rules';
import { useForm } from '../../hooks/useForm';
import {
    Instrument,
    InstrumentApiService,
    InstrumentCategory,
} from '../../services/InstrumentApiService';
import { ApiError } from '../../services/httpClient';
import { instrumentIcon } from '../../utils/instrumentIcon';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';

const EMPTY_DRAFT = { name: '', category: '', sortOrder: '' };

export default function InstrumentManager() {
    const [instruments, setInstruments] = useState<Instrument[]>([]);
    const [categories, setCategories] = useState<InstrumentCategory[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // editingId === 0 — строка создания нового; null — ничего не редактируется
    const [editingId, setEditingId] = useState<number | null>(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const apiService = useMemo(() => new InstrumentApiService(), []);

    // Черновик строки — на общем каркасе форм
    const draft = useForm({
        initial: EMPTY_DRAFT,
        rules: { name: [required('Укажите название.')] },
        fallbackError: 'Не удалось сохранить.',
        onSubmit: async (values) => {
            const input = {
                name: values.name.trim(),
                category: values.category,
                sortOrder: Number(values.sortOrder) || 0,
            };

            if (editingId === 0) {
                await apiService.create(input);
            } else if (editingId !== null) {
                await apiService.update(editingId, input);
            }

            cancel();
            await load();
        },
    });

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const catalog = await apiService.getCatalog();
            setInstruments(catalog.data);
            setCategories(catalog.categories);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить справочник.');
        }

        setLoading(false);
    }, [apiService]);

    useEffect(() => {
        void load();
    }, [load]);

    const startCreate = () => {
        const nextOrder = instruments.reduce((max, i) => Math.max(max, i.sortOrder), 0) + 10;
        draft.setAll({ name: '', category: categories[0]?.value ?? '', sortOrder: String(nextOrder) });
        setEditingId(0);
    };

    const startEdit = (instrument: Instrument) => {
        draft.setAll({
            name: instrument.name,
            category: instrument.category,
            sortOrder: String(instrument.sortOrder),
        });
        setConfirmDeleteId(null);
        setEditingId(instrument.id);
    };

    const cancel = () => {
        setEditingId(null);
    };

    const remove = async (instrument: Instrument) => {
        if (confirmDeleteId !== instrument.id) {
            setConfirmDeleteId(instrument.id);
            return;
        }

        setBusyId(instrument.id);
        setError(null);

        try {
            await apiService.remove(instrument.id);
            await load();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось удалить.');
        }

        setConfirmDeleteId(null);
        setBusyId(null);
    };

    const draftForm = (
        <form className="instr-admin__form" onSubmit={draft.handleSubmit} noValidate>
            <input
                className={`field__input${draft.errors.name ? ' field__input--invalid' : ''}`}
                placeholder="Название"
                value={draft.values.name}
                onChange={(e) => draft.setValue('name', e.currentTarget.value)}
                aria-label="Название инструмента"
                autoFocus
            />
            <select
                className="field__input"
                value={draft.values.category}
                onChange={(e) => draft.setValue('category', e.currentTarget.value)}
                aria-label="Категория"
            >
                {categories.map((c) => (
                    <option key={c.value} value={c.value}>{instrumentIcon(c.value)} {c.label}</option>
                ))}
            </select>
            <input
                className="field__input instr-admin__order"
                type="number"
                placeholder="Порядок"
                value={draft.values.sortOrder}
                onChange={(e) => draft.setValue('sortOrder', e.currentTarget.value)}
                aria-label="Порядок сортировки"
            />
            <div className="instr-admin__form-actions">
                <Button type="submit" size="sm" variant="brass" loading={draft.submitting}>Сохранить</Button>
                <Button type="button" size="sm" variant="ghost" onClick={cancel}>Отмена</Button>
            </div>
            {draft.errors.name && <span className="field__error instr-admin__form-error">{draft.errors.name}</span>}
            {draft.errors.general && <span className="field__error instr-admin__form-error">{draft.errors.general}</span>}
        </form>
    );

    return (
        <div className="instr-admin">
            <div className="instr-admin__toolbar">
                <p className="instr-admin__hint">
                    Общий справочник школы: эти инструменты выбираются в карточках учеников.
                </p>
                {editingId !== 0 && (
                    <Button variant="brass" onClick={startCreate}>+ Новый инструмент</Button>
                )}
            </div>

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card" aria-busy={loading}>
                <table className="instr-admin__table">
                    <thead>
                        <tr>
                            <th>Инструмент</th>
                            <th>Категория</th>
                            <th>Порядок</th>
                            <th aria-label="Действия" />
                        </tr>
                    </thead>
                    <tbody>
                        {editingId === 0 && (
                            <tr>
                                <td colSpan={4}>{draftForm}</td>
                            </tr>
                        )}

                        {instruments.length === 0 && !loading && editingId !== 0 && (
                            <tr>
                                <td colSpan={4}>
                                    <div className="instr-admin__empty">
                                        <span aria-hidden="true">🎼</span> Справочник пуст. Добавьте первый инструмент.
                                    </div>
                                </td>
                            </tr>
                        )}

                        {instruments.map((instrument) => (
                            editingId === instrument.id ? (
                                <tr key={instrument.id}>
                                    <td colSpan={4}>{draftForm}</td>
                                </tr>
                            ) : (
                                <tr key={instrument.id}>
                                    <td className="instr-admin__name">
                                        <span aria-hidden="true">{instrumentIcon(instrument.category)}</span> {instrument.name}
                                    </td>
                                    <td className="instr-admin__category">{instrument.categoryLabel}</td>
                                    <td className="instr-admin__order-cell">{instrument.sortOrder}</td>
                                    <td className="instr-admin__actions">
                                        <button type="button" className="notes__link-btn" onClick={() => startEdit(instrument)}>
                                            Изменить
                                        </button>
                                        <button
                                            type="button"
                                            className={`notes__link-btn${confirmDeleteId === instrument.id ? ' notes__link-btn--danger' : ''}`}
                                            disabled={busyId === instrument.id}
                                            onClick={() => remove(instrument)}
                                        >
                                            {confirmDeleteId === instrument.id ? 'Точно удалить?' : 'Удалить'}
                                        </button>
                                    </td>
                                </tr>
                            )
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
