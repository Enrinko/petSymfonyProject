import { useCallback, useEffect, useMemo, useState } from 'react';
import { uiLocale } from '../../utils/locale';
import { useForm } from '../../hooks/useForm';
import { EventApiService, SchoolEvent } from '../../services/EventApiService';
import { ClientApiService } from '../../services/ClientApiService';
import { RepertoireApiService, RepertoirePiece } from '../../services/RepertoireApiService';
import { ApiError } from '../../services/httpClient';
import { t } from '../../i18n';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';

interface EventCardProps {
    eventId: number;
    printUrl: string;
}

interface ClientOption {
    id: number;
    name: string;
}

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString(uiLocale(),{ day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function EventCard({ eventId, printUrl }: EventCardProps) {
    const [event, setEvent] = useState<SchoolEvent | null>(null);
    const [error, setError] = useState<string | null>(null);

    const [clients, setClients] = useState<ClientOption[]>([]);
    const [readyPieces, setReadyPieces] = useState<RepertoirePiece[]>([]);
    const [busyItemId, setBusyItemId] = useState<number | null>(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

    const eventApi = useMemo(() => new EventApiService(), []);
    const clientApi = useMemo(() => new ClientApiService(), []);
    const repertoireApi = useMemo(() => new RepertoireApiService(), []);

    const load = useCallback(async () => {
        setError(null);

        try {
            setEvent(await eventApi.getEvent(eventId));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.events.card.load_failed', 'Не удалось загрузить мероприятие.'));
        }
    }, [eventApi, eventId]);

    useEffect(() => {
        void load();
        clientApi.getClients(1, '', false, 100)
            .then((page) => setClients(page.data.map((c) => ({ id: c.id, name: c.name }))))
            .catch(() => { /* добавление номеров недоступно без списка */ });
    }, [load, clientApi]);

    // Добавление номера — на общем каркасе: either/or (произведение
    // или текст) — формо-уровневым validate, единая строка ошибки — general
    const add = useForm({
        initial: { client: '', piece: '', customTitle: '' },
        validate: (values): Record<string, string> => {
            if (values.client === '') {
                return { general: t('frontend.events.card.pick_student', 'Выберите ученика.') };
            }

            if (values.piece === '' && values.customTitle.trim() === '') {
                return { general: t('frontend.events.card.pick_piece_or_text', 'Выберите произведение или впишите номер текстом.') };
            }

            return {};
        },
        fallbackError: t('frontend.events.card.add_failed', 'Не удалось добавить номер.'),
        onSubmit: async (values) => {
            const updated = await eventApi.addProgramItem(
                eventId,
                Number(values.client),
                values.piece === '' ? null : Number(values.piece),
                values.piece === '' ? values.customTitle.trim() : null,
            );
            setEvent(updated);
            add.setValue('customTitle', '');
            add.setValue('piece', '');
        },
    });

    const selectedClient = add.values.client;

    // При выборе ученика подтягиваем его «готовые» произведения
    useEffect(() => {
        add.setValue('piece', '');
        setReadyPieces([]);

        if (selectedClient === '') {
            return;
        }

        repertoireApi.getPieces(Number(selectedClient))
            .then((res) => setReadyPieces(res.data.filter((p) => p.status === 'ready' || p.status === 'in_repertoire')))
            .catch(() => { /* можно вписать номер текстом */ });
        // add пересоздаётся каждый рендер — в deps нельзя (зацикливание)
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedClient, repertoireApi]);

    const move = async (itemId: number, direction: 'up' | 'down') => {
        setBusyItemId(itemId);

        try {
            setEvent(await eventApi.moveProgramItem(eventId, itemId, direction));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.events.card.move_failed', 'Не удалось переставить номер.'));
        }

        setBusyItemId(null);
    };

    const removeItem = async (itemId: number) => {
        if (confirmDeleteId !== itemId) {
            setConfirmDeleteId(itemId);
            return;
        }

        setBusyItemId(itemId);

        try {
            setEvent(await eventApi.removeProgramItem(eventId, itemId));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.events.card.remove_failed', 'Не удалось убрать номер.'));
        }

        setConfirmDeleteId(null);
        setBusyItemId(null);
    };

    if (event === null) {
        return error ? <Alert kind="error">{error}</Alert> : <div aria-busy="true">{t('frontend.events.card.loading', 'Загружаем…')}</div>;
    }

    const program = event.program ?? [];

    return (
        <div className="event-card">
            <div className="card event-card__head">
                <div>
                    <span className="events__card-kind">{event.kindLabel}</span>
                    <p className="event-card__meta">
                        {formatDate(event.date)}
                        {event.venue && ` · ${event.venue}`}
                    </p>
                    {event.description && <p className="event-card__desc">{event.description}</p>}
                </div>
                <a className="btn btn--ghost btn--sm" href={printUrl} target="_blank" rel="noreferrer">
                    {t('frontend.events.card.print_program', '🖨 Программа для печати')}
                </a>
            </div>

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card event-card__program">
                <div className="card__header">
                    <h3 className="card__title">{t('frontend.events.card.program_title', 'Программа')}</h3>
                </div>
                <div className="card__body">
                    <form className="event-card__add" onSubmit={add.handleSubmit} noValidate>
                        <select
                            className="field__input"
                            value={add.values.client}
                            onChange={(e) => add.setValue('client', e.currentTarget.value)}
                            aria-label={t('frontend.events.card.student_label', 'Ученик')}
                        >
                            <option value="">{t('frontend.events.card.student_option', '— ученик —')}</option>
                            {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>

                        <select
                            className="field__input"
                            value={add.values.piece}
                            onChange={(e) => add.setValue('piece', e.currentTarget.value)}
                            aria-label={t('frontend.events.card.ready_piece_label', 'Готовое произведение')}
                            disabled={selectedClient === ''}
                        >
                            <option value="">
                                {readyPieces.length === 0 ? t('frontend.events.card.no_ready_pieces', '— нет готовых произведений —') : t('frontend.events.card.piece_option', '— произведение —')}
                            </option>
                            {readyPieces.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.title}{p.composer ? ` — ${p.composer}` : ''}
                                </option>
                            ))}
                        </select>

                        <input
                            className="field__input"
                            placeholder={t('frontend.events.card.custom_title_placeholder', '…или впишите номер текстом')}
                            value={add.values.customTitle}
                            onChange={(e) => add.setValue('customTitle', e.currentTarget.value)}
                            disabled={add.values.piece !== ''}
                            aria-label={t('frontend.events.card.custom_title_label', 'Номер текстом')}
                        />

                        <Button type="submit" size="sm" variant="brass" loading={add.submitting}>{t('frontend.events.card.add_button', 'Добавить')}</Button>
                    </form>
                    {add.errors.general && <span className="field__error">{add.errors.general}</span>}

                    {program.length === 0 && (
                        <p className="rep__empty">{t('frontend.events.card.empty', 'Программа пуста. Соберите её из готовых произведений учеников.')}</p>
                    )}

                    <ol className="event-card__list">
                        {program.map((item, index) => (
                            <li key={item.id} className="event-card__item">
                                <span className="event-card__num">{index + 1}.</span>
                                <span className="event-card__piece">
                                    {item.title}
                                    {item.composer && <span className="event-card__composer"> — {item.composer}</span>}
                                </span>
                                <span className="event-card__performer">{item.clientName}</span>
                                <span className="event-card__item-actions">
                                    <button
                                        type="button"
                                        className="rep__step"
                                        disabled={index === 0 || busyItemId === item.id}
                                        aria-label={t('frontend.events.card.move_up', 'Выше')}
                                        onClick={() => move(item.id, 'up')}
                                    >
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        className="rep__step"
                                        disabled={index === program.length - 1 || busyItemId === item.id}
                                        aria-label={t('frontend.events.card.move_down', 'Ниже')}
                                        onClick={() => move(item.id, 'down')}
                                    >
                                        ↓
                                    </button>
                                    <button
                                        type="button"
                                        className={`notes__link-btn${confirmDeleteId === item.id ? ' notes__link-btn--danger' : ''}`}
                                        disabled={busyItemId === item.id}
                                        onClick={() => removeItem(item.id)}
                                    >
                                        {confirmDeleteId === item.id ? t('frontend.events.card.confirm_delete', 'Точно?') : t('frontend.events.card.remove_button', 'Убрать')}
                                    </button>
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </div>
    );
}
