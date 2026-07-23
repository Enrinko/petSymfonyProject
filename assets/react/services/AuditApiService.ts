import { httpClient } from './httpClient';

export interface AuditEvent {
    id: number;
    occurredAt: string;
    action: string;
    actorId: number | null;
    actorEmail: string | null;
    subjectType: string | null;
    subjectId: string | null;
    ip: string | null;
    payload: Record<string, unknown>;
}

export interface AuditPage {
    events: AuditEvent[];
    total: number;
    page: number;
    perPage: number;
    actions: string[];
}

export interface AuditFilters {
    action?: string;
    actor?: string;
    from?: string;
    to?: string;
}

export class AuditApiService {
    getEvents(page = 1, filters: AuditFilters = {}, perPage = 30): Promise<AuditPage> {
        const params = new URLSearchParams({ page: String(page), perPage: String(perPage) });

        for (const [key, value] of Object.entries(filters)) {
            if (value) {
                params.set(key, value);
            }
        }

        return httpClient.get<AuditPage>(`/api/admin/audit?${params}`);
    }
}
