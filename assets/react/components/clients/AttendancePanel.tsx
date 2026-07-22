import { useEffect, useMemo, useState } from 'react';
import { AttendanceApiService, ClientAttendanceStats } from '../../services/LessonApiService';
import { formatRelative } from '../../utils/relativeTime';

interface AttendancePanelProps {
    clientId: number;
}

const DOT_CLASS: Record<string, string> = {
    attended: 'att-dot--ok',
    missed: 'att-dot--miss',
    cancelled_by_client: 'att-dot--cancel',
    cancelled_by_teacher: 'att-dot--cancel',
};

export default function AttendancePanel({ clientId }: AttendancePanelProps) {
    const [stats, setStats] = useState<ClientAttendanceStats | null>(null);
    const apiService = useMemo(() => new AttendanceApiService(), []);

    useEffect(() => {
        apiService.getStats(clientId)
            .then(setStats)
            .catch(() => { /* блок статистики не критичен для карточки */ });
    }, [apiService, clientId]);

    if (stats === null || (stats.recent.length === 0 && stats.held30 === 0)) {
        return null; // до первых занятий блок не показываем
    }

    return (
        <div className="card att">
            <div className="card__header">
                <h3 className="card__title">Посещаемость</h3>
                {stats.needsAttention && (
                    <span className="badge att__warn" title="2+ пропуска подряд или 3 за месяц">
                        ⚠ требует внимания
                    </span>
                )}
            </div>
            <div className="card__body">
                <div className="att__dots" aria-label="Последние занятия, новые слева">
                    {stats.recent.map((dot) => (
                        <span
                            key={dot.lessonId}
                            className={`att-dot ${DOT_CLASS[dot.attendance] ?? ''}`}
                            title={`${dot.label} · ${formatRelative(dot.startsAt)}`}
                        />
                    ))}
                </div>
                <p className="att__summary">
                    За 30 дней: занятий состоялось <strong>{stats.held30}</strong>,
                    пропущено <strong>{stats.missed30}</strong>.
                </p>
            </div>
        </div>
    );
}
