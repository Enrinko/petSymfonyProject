import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { EventApiService, SchoolEvent } from '../../services/EventApiService';
import { ClientApiService } from '../../services/ClientApiService';
import { RepertoireApiService, RepertoirePiece } from '../../services/RepertoireApiService';
import { ApiError } from '../../services/httpClient';
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
    new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function EventCard({ eventId, printUrl }: EventCardProps) {
    const [event, setEvent] = useState<SchoolEvent | null>(null);
    const [error, setError] = useState<string | null>(null);

    const [clients, setClients] = useState<ClientOption[]>([]);
    const [selectedClient, setSelectedClient] = useState('');
    const [readyPieces, setReadyPieces] = useState<RepertoirePiece[]>([]);
    const [selectedPiece, setSelectedPiece] = useState('');
    const [customTitle, setCustomTitle] = useState('');
    const [adding, setAdding] = useState(false);
    const [addError, setAddError] = useState<string | null>(null);
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
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить мероприятие.');
        }
    }, [eventApi, eventId]);

    useEffect(() => {
        void load();
        clientApi.getClients(1, '', false, 100)
            .then((page) => setClients(page.data.map((c) => ({ id: c.id, name: c.name }))))
            .catch(() => { /* добавление номеров недоступно без списка */ });
    }, [load, clientApi]);

    // При выборе ученика подтягиваем его «готовые» произведения
    useEffect(() => {
        setSelectedPiece('');
        setReadyPieces([]);

        if (selectedClient === '') {
            return;
        }

        repertoireApi.getPieces(Number(selectedClient))
            .then((res) => setReadyPieces(res.data.filter((p) => p.status === 'ready' || p.status === 'in_repertoire')))
            .catch(() => { /* можно вписать номер текстом */ });
    }, [selectedClient, repertoireApi]);

    const handleAdd = async (e: FormEvent) => {
        e.preventDefault();

        if (selectedClient === '') {
            setAddError('Выберите ученика.');
            return;
        }

        if (selectedPiece === '' && customTitle.trim() === '') {
            setAddError('Выберите произведение или впишите номер текстом.');
            return;
        }

        setAdding(true);
        setAddError(null);

        try {
            const updated = await eventApi.addProgramItem(
                eventId,
                Number(selectedClient),
                selectedPiece === '' ? null : Number(selectedPiece),
                selectedPiece === '' ? customTitle.trim() : null,
            );
            setEvent(updated);
            setCustomTitle('');
            setSelectedPiece('');
        } catch (err) {
            setAddError(err instanceof ApiError ? err.message : 'Не удалось добавить номер.');
        }

        setAdding(false);
    };

    const move = async (itemId: number, direction: 'up' | 'down') => {
        setBusyItemId(itemId);

        try {
            setEvent(await eventApi.moveProgramItem(eventId, itemId, direction));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось переставить номер.');
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
            setError(err instanceof ApiError ? err.message : 'Не удалось убрать номер.');
        }

        setConfirmDeleteId(null);
        setBusyItemId(null);
    };

    if (event === null) {
        return error ? <Alert kind="error">{error}</Alert> : <div aria-busy="true">Загружаем…</div>;
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
                    🖨 Программа для печати
                </a>
            </div>

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card event-card__program">
                <div className="card__header">
                    <h3 className="card__title">Программа</h3>
                </div>
                <div className="card__body">
                    <form className="event-card__add" onSubmit={handleAdd} noValidate>
                        <select
                            className="field__input"
                            value={selectedClient}
                            onChange={(e) => setSelectedClient(e.target.value)}
                            aria-label="Ученик"
                        >
                            <option value="">— ученик —</option>
                            {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>

                        <select
                            className="field__input"
                            value={selectedPiece}
                            onChange={(e) => setSelectedPiece(e.target.value)}
                            aria-label="Готовое произведение"
                            disabled={selectedClient === ''}
                        >
                            <option value="">
                                {readyPieces.length === 0 ? '— нет готовых произведений —' : '— произведение —'}
                            </option>
                            {readyPieces.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.title}{p.composer ? ` — ${p.composer}` : ''}
                                </option>
                            ))}
                        </select>

                        <input
                            className="field__input"
                            placeholder="…или впишите номер текстом"
                            value={customTitle}
                            onChange={(e) => setCustomTitle(e.target.value)}
                            disabled={selectedPiece !== ''}
                            aria-label="Номер текстом"
                        />

                        <Button type="submit" size="sm" variant="brass" loading={adding}>Добавить</Button>
                    </form>
                    {addError && <span className="field__error">{addError}</span>}

                    {program.length === 0 && (
                        <p className="rep__empty">Программа пуста. Соберите её из готовых произведений учеников.</p>
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
                                        aria-label="Выше"
                                        onClick={() => move(item.id, 'up')}
                                    >
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        className="rep__step"
                                        disabled={index === program.length - 1 || busyItemId === item.id}
                                        aria-label="Ниже"
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
                                        {confirmDeleteId === item.id ? 'Точно?' : 'Убрать'}
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
