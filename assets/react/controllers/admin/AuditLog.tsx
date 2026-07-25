import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { uiLocale } from '../../utils/locale';
import { AuditApiService, AuditEvent, AuditFilters } from '../../services/AuditApiService';
import { ApiError } from '../../services/httpClient';
import Alert from '../../components/ui/Alert';

// Человекочитаемые названия действий журнала
const ACTION_LABELS: Record<string, string> = {
    'login.succeeded': 'Вход выполнен',
    'login.failed': 'Неудачный вход',
    'login.logged_out': 'Выход',
    'user.roles_changed': 'Роли изменены',
    'user.deactivated': 'Аккаунт деактивирован',
    'user.activated': 'Аккаунт активирован',
    'password.changed': 'Пароль изменён',
    'password.reset_requested': 'Запрошен сброс пароля',
    'password.reset_completed': 'Пароль сброшен по ссылке',
};

const actionLabel = (action: string): string => ACTION_LABELS[action] ?? action;

const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleString(uiLocale(),{
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

const describeSubject = (event: AuditEvent): string => {
    if (event.subjectType === null) {
        return '—';
    }

    return `${event.subjectType} #${event.subjectId ?? '?'}`;
};

const describePayload = (event: AuditEvent): string => {
    const parts: string[] = [];

    if (typeof event.payload.reason === 'string') {
        parts.push(event.payload.reason);
    }

    if (Array.isArray(event.payload.old) && Array.isArray(event.payload.new)) {
        const strip = (roles: unknown[]) => roles
            .filter((r): r is string => typeof r === 'string')
            .map((r) => r.replace('ROLE_', ''))
            .join('+');
        parts.push(`${strip(event.payload.old)} → ${strip(event.payload.new)}`);
    }

    return parts.join('; ') || '—';
};

export default function AuditLog() {
    const api = useMemo(() => new AuditApiService(), []);

    const [events, setEvents] = useState<AuditEvent[]>([]);
    const [actions, setActions] = useState<string[]>([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(30);
    const [filters, setFilters] = useState<AuditFilters>({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const totalPages = Math.max(1, Math.ceil(total / perPage));

    // Гонка запросов: старый ответ не должен перезаписать свежий (см. ClientList)
    const reqId = useRef(0);

    const load = useCallback(async () => {
        const id = ++reqId.current;
        setLoading(true);
        setError(null);

        try {
            const data = await api.getEvents(page, filters);
            if (id !== reqId.current) return;
            setEvents(data.events);
            setActions(data.actions);
            setTotal(data.total);
            setPerPage(data.perPage);
        } catch (err) {
            if (id !== reqId.current) return;
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить журнал.');
        }

        if (id === reqId.current) setLoading(false);
    }, [api, page, filters]);

    useEffect(() => {
        void load();
    }, [load]);

    const applyFilter = (patch: AuditFilters) => {
        setFilters((prev) => ({ ...prev, ...patch }));
        setPage(1);
    };

    return (
        <div className="audit">
            <div className="audit__toolbar">
                <select
                    className="field__input audit__filter"
                    value={filters.action ?? ''}
                    onChange={(e) => applyFilter({ action: e.target.value || undefined })}
                    aria-label="Фильтр по действию"
                >
                    <option value="">Все действия</option>
                    {actions.map((action) => (
                        <option key={action} value={action}>{actionLabel(action)}</option>
                    ))}
                </select>
                <input
                    type="search"
                    className="field__input audit__filter"
                    placeholder="Актор (email)…"
                    value={filters.actor ?? ''}
                    onChange={(e) => applyFilter({ actor: e.target.value || undefined })}
                    aria-label="Фильтр по актору"
                />
                <input
                    type="date"
                    className="field__input audit__filter audit__filter--date"
                    value={filters.from ?? ''}
                    onChange={(e) => applyFilter({ from: e.target.value || undefined })}
                    aria-label="С даты"
                />
                <input
                    type="date"
                    className="field__input audit__filter audit__filter--date"
                    value={filters.to ?? ''}
                    onChange={(e) => applyFilter({ to: e.target.value || undefined })}
                    aria-label="По дату"
                />
                <span className="audit__count">Всего: <strong>{total}</strong></span>
            </div>

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card">
                <table className="audit-table">
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Действие</th>
                            <th>Актор</th>
                            <th>Объект</th>
                            <th>Детали</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        {events.length === 0 && !loading && (
                            <tr>
                                <td colSpan={6}>
                                    <div className="audit__empty">
                                        <span aria-hidden="true">𝄽</span> Записей нет — тишина в зале.
                                    </div>
                                </td>
                            </tr>
                        )}
                        {events.map((event) => (
                            <tr key={event.id}>
                                <td className="audit-table__time">{formatDateTime(event.occurredAt)}</td>
                                <td>
                                    <span className={`audit-badge audit-badge--${event.action.split('.')[0]}`}>
                                        {actionLabel(event.action)}
                                    </span>
                                </td>
                                <td className="audit-table__actor">{event.actorEmail ?? '—'}</td>
                                <td className="audit-table__subject">{describeSubject(event)}</td>
                                <td className="audit-table__payload">{describePayload(event)}</td>
                                <td className="audit-table__ip">{event.ip ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {totalPages > 1 && (
                <div className="users__pagination">
                    <button
                        type="button"
                        className="users__page-btn"
                        disabled={page <= 1 || loading}
                        onClick={() => setPage((p) => p - 1)}
                        aria-label="Предыдущая страница"
                    >
                        ‹
                    </button>
                    <span>{page} / {totalPages}</span>
                    <button
                        type="button"
                        className="users__page-btn"
                        disabled={page >= totalPages || loading}
                        onClick={() => setPage((p) => p + 1)}
                        aria-label="Следующая страница"
                    >
                        ›
                    </button>
                </div>
            )}
        </div>
    );
}
