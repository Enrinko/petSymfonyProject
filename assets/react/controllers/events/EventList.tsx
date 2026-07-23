import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { EventApiService, EventKindValue, SchoolEvent } from '../../services/EventApiService';
import { ApiError } from '../../services/httpClient';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';
import TextField from '../../components/ui/TextField';

interface EventListProps {
    eventsBasePath: string;
}

const KIND_ICON: Record<string, string> = { concert: '🎼', exam: '🎓', contest: '🏆' };

const EMPTY = { title: '', kind: 'concert' as EventKindValue, date: '', time: '18:00', venue: '' };

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function EventList({ eventsBasePath }: EventListProps) {
    const [events, setEvents] = useState<SchoolEvent[]>([]);
    const [showPast, setShowPast] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState(EMPTY);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const apiService = useMemo(() => new EventApiService(), []);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setEvents((await apiService.getEvents(showPast)).data);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить мероприятия.');
        }

        setLoading(false);
    }, [apiService, showPast]);

    useEffect(() => {
        void load();
    }, [load]);

    const handleCreate = async (e: FormEvent) => {
        e.preventDefault();

        if (form.title.trim() === '') {
            setFormErrors({ title: 'Укажите название.' });
            return;
        }
        if (form.date === '') {
            setFormErrors({ date: 'Укажите дату.' });
            return;
        }

        setSaving(true);
        setFormErrors({});

        try {
            const created = await apiService.create({
                title: form.title.trim(),
                kind: form.kind,
                date: new Date(`${form.date}T${form.time || '18:00'}:00`).toISOString(),
                venue: form.venue.trim() || null,
                description: null,
            });
            window.location.assign(`${eventsBasePath}/${created.id}`);
        } catch (err) {
            if (err instanceof ApiError) {
                setFormErrors(err.errors ?? { general: err.message });
            } else {
                setFormErrors({ general: 'Не удалось создать мероприятие.' });
            }
            setSaving(false);
        }
    };

    return (
        <div className="events">
            <div className="events__toolbar">
                <label className="clients__archived-toggle">
                    <input type="checkbox" checked={showPast} onChange={(e) => setShowPast(e.target.checked)} />
                    <span>прошедшие</span>
                </label>
                <Button variant="brass" onClick={() => setCreating((v) => !v)}>
                    {creating ? 'Скрыть форму' : '+ Мероприятие'}
                </Button>
            </div>

            {creating && (
                <form className="card clients__create" onSubmit={handleCreate} noValidate>
                    <div className="clients__create-grid">
                        <TextField
                            id="event-title"
                            label="Название"
                            placeholder="Отчётный концерт"
                            value={form.title}
                            onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                            error={formErrors.title}
                            required
                            autoFocus
                        />
                        <label className="field">
                            <span className="field__label">Вид</span>
                            <select
                                className="field__input"
                                value={form.kind}
                                onChange={(e) => setForm((p) => ({ ...p, kind: e.target.value as EventKindValue }))}
                            >
                                <option value="concert">🎼 Концерт</option>
                                <option value="exam">🎓 Экзамен</option>
                                <option value="contest">🏆 Конкурс</option>
                            </select>
                        </label>
                        <TextField
                            id="event-date"
                            label="Дата"
                            type="date"
                            value={form.date}
                            onChange={(e) => setForm((p) => ({ ...p, date: e.target.value }))}
                            error={formErrors.date}
                            required
                        />
                        <TextField
                            id="event-time"
                            label="Время"
                            type="time"
                            value={form.time}
                            onChange={(e) => setForm((p) => ({ ...p, time: e.target.value }))}
                        />
                        <TextField
                            id="event-venue"
                            label="Площадка"
                            placeholder="Актовый зал"
                            value={form.venue}
                            onChange={(e) => setForm((p) => ({ ...p, venue: e.target.value }))}
                        />
                    </div>
                    {formErrors.general && <Alert kind="error">{formErrors.general}</Alert>}
                    <div className="clients__create-actions">
                        <Button type="submit" variant="brass" loading={saving}>Создать</Button>
                        <Button type="button" variant="ghost" onClick={() => setCreating(false)}>Отмена</Button>
                    </div>
                </form>
            )}

            {error && <Alert kind="error">{error}</Alert>}

            {events.length === 0 && !loading && (
                <p className="events__empty">
                    {showPast ? 'Прошедших мероприятий нет.' : 'Афиша пуста. Создайте первое мероприятие — и соберите программу из готового репертуара.'}
                </p>
            )}

            <div className="events__grid" aria-busy={loading}>
                {events.map((event) => (
                    <a key={event.id} className="card events__card" href={`${eventsBasePath}/${event.id}`}>
                        <span className="events__card-kind">
                            <span aria-hidden="true">{KIND_ICON[event.kind] ?? '🎼'}</span> {event.kindLabel}
                        </span>
                        <span className="events__card-title">{event.title}</span>
                        <span className="events__card-meta">
                            {formatDate(event.date)}
                            {event.venue && ` · ${event.venue}`}
                        </span>
                        <span className="events__card-count">
                            {event.programCount > 0 ? `Номеров в программе: ${event.programCount}` : 'Программа не составлена'}
                        </span>
                    </a>
                ))}
            </div>
        </div>
    );
}
