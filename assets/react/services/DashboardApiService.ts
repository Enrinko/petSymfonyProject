import { httpClient } from './httpClient';

export interface RecentNote {
    noteId: number;
    clientId: number;
    clientName: string;
    preview: string;
    createdAt: string;
}

export interface DashboardData {
    clientsTotal: number;
    clientsNewThisMonth: number;
    recentNotes: RecentNote[];
}

export class DashboardApiService {
    get(): Promise<DashboardData> {
        return httpClient.get<DashboardData>('/api/dashboard');
    }
}
