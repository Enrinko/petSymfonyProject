import { httpClient } from './httpClient';

export interface Client {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    comment: string | null;
    tags: string[];
    createdAt: string;
    updatedAt: string | null;
    archivedAt: string | null;
}

export interface ClientPage {
    data: Client[];
    total: number;
    page: number;
    limit: number;
}

export interface ClientInput {
    name: string;
    email?: string | null;
    phone?: string | null;
    comment?: string | null;
    tags?: string[];
}

export class ClientApiService {
    getClients(page = 1, search = '', archived = false, limit = 20, tags: string[] = []): Promise<ClientPage> {
        const params = new URLSearchParams({
            page: String(page),
            limit: String(limit),
            search,
        });

        if (archived) {
            params.set('archived', '1');
        }

        if (tags.length > 0) {
            params.set('tags', tags.join(','));
        }

        return httpClient.get<ClientPage>(`/api/clients?${params}`);
    }

    getClient(id: number): Promise<Client> {
        return httpClient.get<Client>(`/api/clients/${id}`);
    }

    createClient(input: ClientInput): Promise<Client> {
        return httpClient.post<Client>('/api/clients', input);
    }

    updateClient(id: number, input: ClientInput): Promise<Client> {
        return httpClient.put<Client>(`/api/clients/${id}`, input);
    }

    archiveClient(id: number): Promise<void> {
        return httpClient.del(`/api/clients/${id}`);
    }

    restoreClient(id: number): Promise<Client> {
        return httpClient.patch<Client>(`/api/clients/${id}/restore`);
    }
}
