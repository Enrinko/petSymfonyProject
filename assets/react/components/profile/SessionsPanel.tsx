import { useCallback, useEffect, useMemo, useState } from 'react';
import { ProfileApiService, ProfileSession } from '../../services/ProfileApiService';
import { ApiError } from '../../services/httpClient';
import { playSound } from '../../utils/sound';
import { formatRelative } from '../../utils/relativeTime';
import Alert from '../ui/Alert';
import Button from '../ui/Button';

const osGlyph = (os: string): string => {
    switch (os) {
        case 'Windows': return '⊞';
        case 'macOS':
        case 'iOS': return '';
        case 'Android': return '🤖';
        case 'Linux': return '🐧';
        default: return '♪';
    }
};

export default function SessionsPanel() {
    const api = useMemo(() => new ProfileApiService(), []);

    const [sessions, setSessions] = useState<ProfileSession[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | 'all' | null>(null);
    const [confirmAll, setConfirmAll] = useState(false);

    const load = useCallback(async () => {
        try {
            setSessions((await api.getSessions()).sessions);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить сессии.');
        }
    }, [api]);

    useEffect(() => {
        void load();
    }, [load]);

    const terminate = async (id: number) => {
        setBusyId(id);
        setError(null);

        try {
            await api.terminateSession(id);
            playSound('notify');
            await load();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось завершить сессию.');
        }

        setBusyId(null);
    };

    const terminateOthers = async () => {
        setBusyId('all');
        setError(null);

        try {
            await api.terminateOtherSessions();
            playSound('notify');
            setConfirmAll(false);
            await load();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось завершить сессии.');
        }

        setBusyId(null);
    };

    const others = sessions.filter((s) => !s.current);

    return (
        <div className="sessions">
            {error && <Alert kind="error">{error}</Alert>}

            <ul className="sessions__list">
                {sessions.map((session) => (
                    <li key={session.id} className={`sessions__item${session.current ? ' sessions__item--current' : ''}`}>
                        <span className="sessions__glyph" aria-hidden="true">{osGlyph(session.os)}</span>
                        <span className="sessions__meta">
                            <span className="sessions__device">
                                {session.browser}{session.os ? ` · ${session.os}` : ''}
                                {session.current && <span className="badge sessions__badge">текущая</span>}
                            </span>
                            <span className="sessions__details">
                                {session.ip ?? 'IP неизвестен'}
                                {' · '}
                                {session.current ? 'сейчас онлайн' : `активность ${formatRelative(session.lastSeenAt)}`}
                            </span>
                        </span>
                        {!session.current && (
                            <Button
                                size="sm"
                                variant="ghost"
                                loading={busyId === session.id}
                                onClick={() => terminate(session.id)}
                            >
                                Завершить
                            </Button>
                        )}
                    </li>
                ))}
            </ul>

            {others.length > 0 && (
                <div className="sessions__footer">
                    {confirmAll ? (
                        <>
                            <span className="sessions__confirm">Завершить {others.length} сеанс(а)?</span>
                            <Button size="sm" variant="brass" loading={busyId === 'all'} onClick={terminateOthers}>
                                Да, завершить
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setConfirmAll(false)}>
                                Отмена
                            </Button>
                        </>
                    ) : (
                        <Button size="sm" variant="ghost" onClick={() => setConfirmAll(true)}>
                            Завершить все, кроме текущей
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
