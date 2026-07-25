import { useCallback, useEffect, useMemo, useState } from 'react';
import { uiLocale } from '../../utils/locale';
import { required } from '../../hooks/rules';
import { useForm } from '../../hooks/useForm';
import { EventApiService, EventKindValue, SchoolEvent } from '../../services/EventApiService';
import { ApiError } from '../../services/httpClient';
import { t } from '../../i18n';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';
import TextField from '../../components/ui/TextField';

interface EventListProps {
    eventsBasePath: string;
}

const KIND_ICON: Record<string, string> = { concert: '🎼', exam: '🎓', contest: '🏆' };

const EMPTY = { title: '', kind: 'concert' as EventKindValue, date: '', time: '18:00', venue: '' };

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString(uiLocale(),{ day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function EventList({ eventsBasePath }: EventListProps) {
    const [events, setEvents] = useState<SchoolEvent[]>([]);
    const [showPast, setShowPast] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [creating, setCreating] = useState(false);

    const apiService = useMemo(() => new EventApiService(), []);

    const form = useForm({
        initial: EMPTY,
        rules: {
            title: [required(t('frontend.events.list.error_title_required', 'Укажите название.'))],
            date: [required(t('frontend.events.list.error_date_required', 'Укажите дату.'))],
        },
        fallbackError: t('frontend.events.list.error_create', 'Не удалось создать мероприятие.'),
        onSubmit: async (values) => {
            const created = await apiService.create({
                title: values.title.trim(),
                kind: values.kind as EventKindValue,
                date: new Date(`${values.date}T${values.time || '18:00'}:00`).toISOString(),
                venue: values.venue.trim() || null,
                description: null,
            });
            window.location.assign(`${eventsBasePath}/${created.id}`);
        },
    });

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setEvents((await apiService.getEvents(showPast)).data);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.events.list.error_load', 'Не удалось загрузить мероприятия.'));
        }

        setLoading(false);
    }, [apiService, showPast]);

    useEffect(() => {
        void load();
    }, [load]);

    return (
        <div className="events">
            <div className="events__toolbar">
                <label className="clients__archived-toggle">
                    <input type="checkbox" checked={showPast} onChange={(e) => setShowPast(e.target.checked)} />
                    <span>{t('frontend.events.list.show_past', 'прошедшие')}</span>
                </label>
                <Button variant="brass" onClick={() => setCreating((v) => !v)}>
                    {creating ? t('frontend.events.list.hide_form', 'Скрыть форму') : t('frontend.events.list.add_event', '+ Мероприятие')}
                </Button>
            </div>

            {creating && (
                <form className="card clients__create" onSubmit={form.handleSubmit} noValidate>
                    <div className="clients__create-grid">
                        <TextField
                            id="event-title"
                            label={t('frontend.events.list.field_title', 'Название')}
                            placeholder={t('frontend.events.list.field_title_placeholder', 'Отчётный концерт')}
                            required
                            autoFocus
                            {...form.fieldProps('title')}
                        />
                        <label className="field">
                            <span className="field__label">{t('frontend.events.list.field_kind', 'Вид')}</span>
                            <select
                                className="field__input"
                                value={form.values.kind}
                                onChange={(e) => form.setValue('kind', e.currentTarget.value)}
                            >
                                <option value="concert">{t('frontend.events.list.kind_concert', '🎼 Концерт')}</option>
                                <option value="exam">{t('frontend.events.list.kind_exam', '🎓 Экзамен')}</option>
                                <option value="contest">{t('frontend.events.list.kind_contest', '🏆 Конкурс')}</option>
                            </select>
                        </label>
                        <TextField
                            id="event-date"
                            label={t('frontend.events.list.field_date', 'Дата')}
                            type="date"
                            required
                            {...form.fieldProps('date')}
                        />
                        <TextField
                            id="event-time"
                            label={t('frontend.events.list.field_time', 'Время')}
                            type="time"
                            {...form.fieldProps('time')}
                        />
                        <TextField
                            id="event-venue"
                            label={t('frontend.events.list.field_venue', 'Площадка')}
                            placeholder={t('frontend.events.list.field_venue_placeholder', 'Актовый зал')}
                            {...form.fieldProps('venue')}
                        />
                    </div>
                    {form.errors.general && <Alert kind="error">{form.errors.general}</Alert>}
                    <div className="clients__create-actions">
                        <Button type="submit" variant="brass" loading={form.submitting}>{t('frontend.events.list.submit', 'Создать')}</Button>
                        <Button type="button" variant="ghost" onClick={() => setCreating(false)}>{t('frontend.events.list.cancel', 'Отмена')}</Button>
                    </div>
                </form>
            )}

            {error && <Alert kind="error">{error}</Alert>}

            {events.length === 0 && !loading && (
                <p className="events__empty">
                    {showPast ? t('frontend.events.list.empty_past', 'Прошедших мероприятий нет.') : t('frontend.events.list.empty_upcoming', 'Афиша пуста. Создайте первое мероприятие — и соберите программу из готового репертуара.')}
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
                            {event.programCount > 0 ? t('frontend.events.list.program_count', 'Номеров в программе: %count%', {count: event.programCount}) : t('frontend.events.list.program_empty', 'Программа не составлена')}
                        </span>
                    </a>
                ))}
            </div>
        </div>
    );
}
