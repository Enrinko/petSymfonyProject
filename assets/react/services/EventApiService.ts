import { httpClient } from './httpClient';

export type EventKindValue = 'concert' | 'exam' | 'contest';

export interface ProgramItem {
    id: number;
    clientId: number;
    clientName: string;
    title: string;
    composer: string | null;
    sortOrder: number;
}

export interface SchoolEvent {
    id: number;
    title: string;
    kind: EventKindValue;
    kindLabel: string;
    date: string;
    venue: string | null;
    description: string | null;
    programCount: number;
    program?: ProgramItem[];
}

export interface EventInput {
    title: string;
    kind: EventKindValue;
    date: string;
    venue: string | null;
    description: string | null;
}

export class EventApiService {
    getEvents(past = false): Promise<{ data: SchoolEvent[] }> {
        return httpClient.get<{ data: SchoolEvent[] }>(`/api/events${past ? '?past=1' : ''}`);
    }

    getEvent(id: number): Promise<SchoolEvent> {
        return httpClient.get<SchoolEvent>(`/api/events/${id}`);
    }

    create(input: EventInput): Promise<SchoolEvent> {
        return httpClient.post<SchoolEvent>('/api/events', input);
    }

    update(id: number, input: EventInput): Promise<SchoolEvent> {
        return httpClient.patch<SchoolEvent>(`/api/events/${id}`, input);
    }

    remove(id: number): Promise<void> {
        return httpClient.del(`/api/events/${id}`);
    }

    addProgramItem(eventId: number, clientId: number, pieceId: number | null, customTitle: string | null): Promise<SchoolEvent> {
        return httpClient.post<SchoolEvent>(`/api/events/${eventId}/program`, { clientId, pieceId, customTitle });
    }

    moveProgramItem(eventId: number, itemId: number, direction: 'up' | 'down'): Promise<SchoolEvent> {
        return httpClient.patch<SchoolEvent>(`/api/events/${eventId}/program/${itemId}/move`, { direction });
    }

    removeProgramItem(eventId: number, itemId: number): Promise<SchoolEvent> {
        return httpClient.del<SchoolEvent>(`/api/events/${eventId}/program/${itemId}`);
    }
}
