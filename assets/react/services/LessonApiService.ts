import { httpClient } from './httpClient';

export interface Lesson {
    id: number;
    clientId: number;
    clientName: string;
    instrumentId: number | null;
    instrumentName: string | null;
    instrumentCategory: string | null;
    startsAt: string;
    endsAt: string;
    durationMinutes: number;
    status: 'planned' | 'completed' | 'cancelled';
    statusLabel: string;
    comment: string | null;
    cancelReason: string | null;
}

export interface WeekSchedule {
    weekStart: string;
    weekEnd: string;
    lessons: Lesson[];
}

export interface ScheduleLessonInput {
    clientId: number;
    instrumentId: number | null;
    startsAt: string;
    durationMinutes: number;
    comment: string | null;
}

export class LessonApiService {
    getWeek(date: string): Promise<WeekSchedule> {
        return httpClient.get<WeekSchedule>(`/api/lessons?date=${encodeURIComponent(date)}`);
    }

    schedule(input: ScheduleLessonInput): Promise<Lesson> {
        return httpClient.post<Lesson>('/api/lessons', input);
    }

    reschedule(id: number, startsAt: string, durationMinutes: number): Promise<Lesson> {
        return httpClient.patch<Lesson>(`/api/lessons/${id}/reschedule`, { startsAt, durationMinutes });
    }

    complete(id: number): Promise<Lesson> {
        return httpClient.patch<Lesson>(`/api/lessons/${id}/complete`);
    }

    cancel(id: number, reason: string): Promise<Lesson> {
        return httpClient.patch<Lesson>(`/api/lessons/${id}/cancel`, { reason });
    }
}
