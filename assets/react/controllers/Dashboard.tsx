import { useEffect, useMemo, useState } from 'react';
import { DashboardApiService, DashboardData } from '../services/DashboardApiService';
import { ApiError } from '../services/httpClient';
import { formatRelative } from '../utils/relativeTime';
import { t } from '../i18n';
import Alert from '../components/ui/Alert';

interface DashboardProps {
    clientsPath: string;
}

export default function Dashboard({ clientsPath }: DashboardProps) {
    const [data, setData] = useState<DashboardData | null>(null);
    const [error, setError] = useState<string | null>(null);
    const apiService = useMemo(() => new DashboardApiService(), []);

    useEffect(() => {
        apiService.get()
            .then(setData)
            .catch((err) => setError(err instanceof ApiError ? err.message : t('frontend.dashboard.load_error', 'Не удалось загрузить сводку.')));
    }, [apiService]);

    if (error) {
        return <Alert kind="error">{error}</Alert>;
    }

    const loading = data === null;

    return (
        <div className="dash-live">
            <div className="dash-live__stats">
                <a className="dash-live__stat" href={clientsPath}>
                    <span className="dash-live__stat-label">{t('frontend.dashboard.clients_total_label', 'Учеников всего')}</span>
                    <span className={`dash-live__stat-value${loading ? ' dash-live__skeleton' : ''}`}>
                        {loading ? '' : data.clientsTotal}
                    </span>
                    <span className="dash-live__stat-hint">{t('frontend.dashboard.open_list', 'открыть список →')}</span>
                </a>

                <div className="dash-live__stat">
                    <span className="dash-live__stat-label">{t('frontend.dashboard.new_this_month_label', 'Новых за месяц')}</span>
                    <span className={`dash-live__stat-value${loading ? ' dash-live__skeleton' : ''}`}>
                        {loading ? '' : data.clientsNewThisMonth}
                    </span>
                    <span className="dash-live__stat-hint">{t('frontend.dashboard.since_first_hint', 'с 1-го числа')}</span>
                </div>
            </div>

            <div className="card dash-live__notes">
                <div className="card__header">
                    <h3 className="card__title">{t('frontend.dashboard.recent_notes_title', 'Последние заметки')}</h3>
                </div>
                <div className="card__body">
                    {loading && <div className="dash-live__notes-loading">{t('frontend.dashboard.loading', 'Загружаем…')}</div>}

                    {!loading && data.recentNotes.length === 0 && (
                        <p className="dash-live__empty">
                            {t('frontend.dashboard.notes_empty', 'Пока тишина. Заметки появятся здесь после первых занятий с учениками.')}
                        </p>
                    )}

                    <ul className="dash-live__note-list">
                        {!loading && data.recentNotes.map((note) => (
                            <li key={note.noteId} className="dash-live__note">
                                <a className="dash-live__note-client" href={`${clientsPath}/${note.clientId}`}>
                                    {note.clientName}
                                </a>
                                <time className="dash-live__note-time" dateTime={note.createdAt} title={note.createdAt}>
                                    {formatRelative(note.createdAt)}
                                </time>
                                <p className="dash-live__note-preview">{note.preview}</p>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}
