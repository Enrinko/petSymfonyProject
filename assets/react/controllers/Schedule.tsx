import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Lesson, LessonApiService } from '../services/LessonApiService';
import { ClientApiService } from '../services/ClientApiService';
import { InstrumentApiService, Instrument } from '../services/InstrumentApiService';
import { ApiError } from '../services/httpClient';
import { instrumentIcon } from '../utils/instrumentIcon';
import {
    DAY_NAMES,
    addDays,
    formatDayLabel,
    formatTime,
    isSameDay,
    mondayOf,
    toDateParam,
} from '../utils/week';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import TextField from '../components/ui/TextField';
import { t } from '../i18n';

interface ScheduleProps {
    clientsPath: string;
}

interface ClientOption {
    id: number;
    name: string;
}

const HOURS = Array.from({ length: 15 }, (_, i) => i + 8); // 8:00–22:00

const emptyDraft = { clientId: '', instrumentId: '', date: '', time: '', durationMinutes: '45', comment: '' };

export default function Schedule({ clientsPath }: ScheduleProps) {
    const [monday, setMonday] = useState(() => mondayOf(new Date()));
    const [lessons, setLessons] = useState<Lesson[]>([]);
    // «Сейчас» фиксируется при загрузке недели: рендер остаётся чистым (без Date.now в JSX)
    const [nowTs, setNowTs] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [clients, setClients] = useState<ClientOption[]>([]);
    const [catalog, setCatalog] = useState<Instrument[]>([]);

    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [draft, setDraft] = useState(emptyDraft);
    const [draftErrors, setDraftErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const lessonApi = useMemo(() => new LessonApiService(), []);
    const clientApi = useMemo(() => new ClientApiService(), []);
    const instrumentApi = useMemo(() => new InstrumentApiService(), []);

    const days = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(monday, i)), [monday]);

    // token — метка устаревания (см. useEffect ниже). Плоский объект, не React-ref:
    // loadWeek течёт в JSX через act()→complete(), а write в ref из render правило
    // react-hooks/refs запрещает. Из act() зовём без token — перезагрузка применяется.
    const loadWeek = useCallback(async (token?: { stale: boolean }) => {
        setLoading(true);
        setError(null);

        try {
            const week = await lessonApi.getWeek(toDateParam(monday));
            if (token?.stale) return;
            setLessons(week.lessons);
            setNowTs(Date.now());
        } catch (err) {
            if (token?.stale) return;
            setError(err instanceof ApiError ? err.message : t('frontend.schedule.load_error', 'Не удалось загрузить расписание.'));
        }

        if (!token?.stale) setLoading(false);
    }, [lessonApi, monday]);

    useEffect(() => {
        // Гонка: быстрая смена недели помечает ответ предыдущей устаревшим
        const token = { stale: false };
        void loadWeek(token);

        return () => { token.stale = true; };
    }, [loadWeek]);

    useEffect(() => {
        clientApi.getClients(1, '', false, 100)
            .then((page) => setClients(page.data.map((c) => ({ id: c.id, name: c.name }))))
            .catch(() => { /* без списка учеников создать нельзя, покажем подсказку */ });
        instrumentApi.getCatalog()
            .then((c) => setCatalog(c.data))
            .catch(() => { /* инструмент необязателен */ });
    }, [clientApi, instrumentApi]);

    const openCreate = (day?: Date, hour?: number) => {
        setEditingId(null);
        setDraft({
            ...emptyDraft,
            clientId: clients[0] ? String(clients[0].id) : '',
            date: day ? toDateParam(day) : toDateParam(days[0]),
            time: hour !== undefined ? `${String(hour).padStart(2, '0')}:00` : '15:00',
        });
        setDraftErrors({});
        setModalOpen(true);
    };

    const openEdit = (lesson: Lesson) => {
        const start = new Date(lesson.startsAt);
        setEditingId(lesson.id);
        setDraft({
            clientId: String(lesson.clientId),
            instrumentId: lesson.instrumentId ? String(lesson.instrumentId) : '',
            date: toDateParam(start),
            time: formatTime(lesson.startsAt),
            durationMinutes: String(lesson.durationMinutes),
            comment: lesson.comment ?? '',
        });
        setDraftErrors({});
        setModalOpen(true);
    };

    const setField = (name: keyof typeof emptyDraft) =>
        (e: FormEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
            // value читаем СРАЗУ: e.currentTarget обнуляется до вызова updater'а setDraft
            const { value } = e.currentTarget;
            setDraft((prev) => ({ ...prev, [name]: value }));
        };

    const submit = async (e: FormEvent) => {
        e.preventDefault();

        if (!draft.clientId) {
            setDraftErrors({ clientId: t('frontend.schedule.select_student', 'Выберите ученика.') });
            return;
        }
        if (!draft.date || !draft.time) {
            setDraftErrors({ time: t('frontend.schedule.date_time_required', 'Укажите дату и время.') });
            return;
        }

        setSaving(true);
        setDraftErrors({});

        // Локальное время без сдвига: собираем ISO с оффсетом браузера
        const startsAt = new Date(`${draft.date}T${draft.time}:00`).toISOString();
        const duration = Number(draft.durationMinutes) || 45;

        try {
            if (editingId === null) {
                await lessonApi.schedule({
                    clientId: Number(draft.clientId),
                    instrumentId: draft.instrumentId ? Number(draft.instrumentId) : null,
                    startsAt,
                    durationMinutes: duration,
                    comment: draft.comment.trim() || null,
                });
            } else {
                await lessonApi.reschedule(editingId, startsAt, duration);
            }

            setModalOpen(false);
            await loadWeek();
        } catch (err) {
            if (err instanceof ApiError) {
                setDraftErrors(err.errors ?? { general: err.message });
            } else {
                setDraftErrors({ general: t('frontend.schedule.save_error', 'Не удалось сохранить занятие.') });
            }
        }

        setSaving(false);
    };

    const act = async (fn: () => Promise<unknown>) => {
        setError(null);
        try {
            await fn();
            await loadWeek();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.schedule.action_failed', 'Действие не выполнено.'));
        }
    };

    const complete = (lesson: Lesson) => act(() => lessonApi.complete(lesson.id));
    const miss = (lesson: Lesson) => act(() => lessonApi.miss(lesson.id));

    const [cancelReason, setCancelReason] = useState('');
    const [cancelBy, setCancelBy] = useState<'client' | 'teacher'>('client');
    const [cancelError, setCancelError] = useState<string | null>(null);

    const submitCancel = async (lesson: Lesson) => {
        if (cancelReason.trim() === '') {
            setCancelError(t('frontend.schedule.cancel_reason_required', 'Укажите причину отмены.'));
            return;
        }

        setCancelError(null);
        await act(() => lessonApi.cancel(lesson.id, cancelReason.trim(), cancelBy));
        setCancelReason('');
        setModalOpen(false);
    };

    const isPast = (lesson: Lesson) => new Date(lesson.startsAt).getTime() <= nowTs;

    return (
        <div className="sched">
            <div className="sched__toolbar">
                <div className="sched__nav">
                    <button type="button" className="sched__nav-btn" onClick={() => setMonday(addDays(monday, -7))} aria-label={t('frontend.schedule.prev_week', 'Прошлая неделя')}>‹</button>
                    <span className="sched__range">{formatDayLabel(days[0])} – {formatDayLabel(days[6])}</span>
                    <button type="button" className="sched__nav-btn" onClick={() => setMonday(addDays(monday, 7))} aria-label={t('frontend.schedule.next_week', 'Следующая неделя')}>›</button>
                    <button type="button" className="sched__today" onClick={() => setMonday(mondayOf(new Date()))}>{t('frontend.schedule.today', 'сегодня')}</button>
                </div>
                <Button variant="brass" onClick={() => openCreate()} disabled={clients.length === 0}>
                    {t('frontend.schedule.add_lesson', '+ Занятие')}
                </Button>
            </div>

            {clients.length === 0 && (
                <Alert kind="error">
                    {t('frontend.schedule.no_clients_prefix', 'Сначала добавьте учеников — ')}<a href={clientsPath}>{t('frontend.schedule.no_clients_link', 'раздел «Ученики»')}</a>.
                </Alert>
            )}
            {error && <Alert kind="error">{error}</Alert>}

            <div className="card sched__grid-wrap" aria-busy={loading}>
                <div className="sched__grid">
                    <div className="sched__corner" />
                    {days.map((day, i) => (
                        <div key={i} className={`sched__day-head${isSameDay(new Date().toISOString(), day) ? ' sched__day-head--today' : ''}`}>
                            <span className="sched__day-name">{DAY_NAMES[i]}</span>
                            <span className="sched__day-date">{formatDayLabel(day)}</span>
                        </div>
                    ))}

                    {HOURS.map((hour) => (
                        <div key={hour} className="sched__row" style={{ gridRow: 'span 1' }}>
                            <div className="sched__hour">{String(hour).padStart(2, '0')}:00</div>
                            {days.map((day, di) => (
                                <button
                                    key={di}
                                    type="button"
                                    className="sched__cell"
                                    onClick={() => openCreate(day, hour)}
                                    aria-label={t('frontend.schedule.add_lesson_aria', 'Добавить занятие %day% %hour%:00', { day: formatDayLabel(day), hour })}
                                >
                                    {lessons
                                        .filter((l) => isSameDay(l.startsAt, day) && new Date(l.startsAt).getHours() === hour)
                                        .map((l) => (
                                            <span
                                                key={l.id}
                                                className={`sched__lesson sched__lesson--${l.status}`}
                                                role="button"
                                                tabIndex={0}
                                                onClick={(e) => { e.stopPropagation(); openEdit(l); }}
                                                onKeyDown={(e) => { if (e.key === 'Enter') { e.stopPropagation(); openEdit(l); } }}
                                            >
                                                <span className="sched__lesson-time">
                                                    {formatTime(l.startsAt)}
                                                    {l.attendance === 'attended' && <span className="sched__mark sched__mark--ok" title={t('frontend.schedule.mark_present', 'Был')}>✓</span>}
                                                    {l.attendance === 'missed' && <span className="sched__mark sched__mark--miss" title={t('frontend.schedule.mark_missed', 'Пропустил')}>✗</span>}
                                                </span>
                                                <span className="sched__lesson-client">
                                                    {l.instrumentCategory && <span aria-hidden="true">{instrumentIcon(l.instrumentCategory)} </span>}
                                                    {l.clientName}
                                                </span>
                                                {l.status === 'planned' && isPast(l) && (
                                                    <span className="sched__quick">
                                                        <button
                                                            type="button"
                                                            className="sched__quick-btn sched__quick-btn--ok"
                                                            title={t('frontend.schedule.mark_present', 'Был')}
                                                            onClick={(e) => { e.stopPropagation(); void complete(l); }}
                                                        >
                                                            ✓
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="sched__quick-btn sched__quick-btn--miss"
                                                            title={t('frontend.schedule.quick_missed', 'Не пришёл')}
                                                            onClick={(e) => { e.stopPropagation(); void miss(l); }}
                                                        >
                                                            ✗
                                                        </button>
                                                    </span>
                                                )}
                                            </span>
                                        ))}
                                </button>
                            ))}
                        </div>
                    ))}
                </div>
            </div>

            {modalOpen && (
                <div
                    className="palette"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('frontend.schedule.dialog_label', 'Занятие')}
                    onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false); }}
                >
                    <div className="sched__modal">
                        <h3 className="sched__modal-title">{editingId === null ? t('frontend.schedule.modal_title_new', 'Новое занятие') : t('frontend.schedule.modal_title_edit', 'Перенос занятия')}</h3>
                        <form onSubmit={submit} noValidate className="sched__form">
                            <label className="field">
                                <span className="field__label">{t('frontend.schedule.field_student', 'Ученик')}</span>
                                <select className="field__input" value={draft.clientId} onChange={setField('clientId')} disabled={editingId !== null}>
                                    {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                {draftErrors.clientId && <span className="field__error">{draftErrors.clientId}</span>}
                            </label>

                            {editingId === null && (
                                <label className="field">
                                    <span className="field__label">{t('frontend.schedule.field_instrument', 'Инструмент')}</span>
                                    <select className="field__input" value={draft.instrumentId} onChange={setField('instrumentId')}>
                                        <option value="">{t('frontend.schedule.instrument_none', '— не указан —')}</option>
                                        {catalog.map((i) => <option key={i.id} value={i.id}>{instrumentIcon(i.category)} {i.name}</option>)}
                                    </select>
                                </label>
                            )}

                            <div className="sched__form-row">
                                <TextField id="lesson-date" label={t('frontend.schedule.field_date', 'Дата')} type="date" value={draft.date} onChange={setField('date')} />
                                <TextField id="lesson-time" label={t('frontend.schedule.field_time', 'Время')} type="time" value={draft.time} onChange={setField('time')} />
                                <TextField id="lesson-dur" label={t('frontend.schedule.field_minutes', 'Минут')} type="number" value={draft.durationMinutes} onChange={setField('durationMinutes')} />
                            </div>
                            {draftErrors.time && <span className="field__error">{draftErrors.time}</span>}
                            {draftErrors.general && <Alert kind="error">{draftErrors.general}</Alert>}

                            <div className="sched__modal-actions">
                                <Button type="submit" variant="brass" loading={saving}>
                                    {editingId === null ? t('frontend.schedule.submit_create', 'Запланировать') : t('frontend.schedule.submit_edit', 'Перенести')}
                                </Button>
                                <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>{t('frontend.schedule.cancel', 'Отмена')}</Button>
                            </div>
                        </form>

                        {editingId !== null && (() => {
                            const lesson = lessons.find((l) => l.id === editingId);
                            if (!lesson || lesson.status !== 'planned') {
                                return null;
                            }
                            return (
                                <div className="sched__modal-status">
                                    {isPast(lesson) && (
                                        <div className="sched__outcome">
                                            <span className="field__label">{t('frontend.schedule.outcome_label', 'Итог занятия')}</span>
                                            <div className="sched__outcome-btns">
                                                <Button type="button" size="sm" variant="brass" onClick={() => { void complete(lesson); setModalOpen(false); }}>
                                                    {t('frontend.schedule.attended_action', '✓ Был')}
                                                </Button>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => { void miss(lesson); setModalOpen(false); }}>
                                                    {t('frontend.schedule.missed_action', '✗ Не пришёл')}
                                                </Button>
                                            </div>
                                        </div>
                                    )}

                                    <div className="sched__cancel">
                                        <span className="field__label">{t('frontend.schedule.cancel_section', 'Отмена занятия')}</span>
                                        <input
                                            className="field__input"
                                            placeholder={t('frontend.schedule.cancel_reason_placeholder', 'Причина отмены')}
                                            value={cancelReason}
                                            onChange={(e) => setCancelReason(e.target.value)}
                                            aria-label={t('frontend.schedule.cancel_reason_placeholder', 'Причина отмены')}
                                        />
                                        <div className="sched__cancel-by" role="radiogroup" aria-label={t('frontend.schedule.cancelled_by_label', 'Кто отменил')}>
                                            <label>
                                                <input type="radio" name="cancel-by" checked={cancelBy === 'client'} onChange={() => setCancelBy('client')} />
                                                <span>{t('frontend.schedule.cancelled_by_client', 'отменил ученик')}</span>
                                            </label>
                                            <label>
                                                <input type="radio" name="cancel-by" checked={cancelBy === 'teacher'} onChange={() => setCancelBy('teacher')} />
                                                <span>{t('frontend.schedule.cancelled_by_teacher', 'отменяю я')}</span>
                                            </label>
                                        </div>
                                        {cancelError && <span className="field__error">{cancelError}</span>}
                                        <Button type="button" size="sm" variant="ghost" onClick={() => void submitCancel(lesson)}>
                                            {t('frontend.schedule.cancel_submit', 'Отменить занятие')}
                                        </Button>
                                    </div>
                                </div>
                            );
                        })()}
                    </div>
                </div>
            )}
        </div>
    );
}
