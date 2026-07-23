import { httpClient } from './httpClient';

export interface ClientHit {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    archived: boolean;
}

export interface TagHit {
    name: string;
    usageCount: number;
}

export interface NoteHit {
    id: number;
    clientId: number;
    clientName: string;
    snippet: string;
    createdAt: string;
}

export interface SearchResults {
    clients: ClientHit[];
    tags: TagHit[];
    notes: NoteHit[];
}

export class SearchApiService {
    search(query: string, signal?: AbortSignal): Promise<SearchResults> {
        return httpClient.get<SearchResults>(`/api/search?q=${encodeURIComponent(query)}`, { signal });
    }
}
