import { httpClient } from './httpClient';

export interface Note {
    id: number;
    content: string;
    authorId: number;
    authorEmail: string;
    createdAt: string;
    updatedAt: string | null;
    manageable: boolean;
}

export interface NotesPage {
    data: Note[];
    total: number;
    page: number;
    limit: number;
}

export class NoteApiService {
    getNotes(clientId: number, page = 1, limit = 20): Promise<NotesPage> {
        const params = new URLSearchParams({ page: String(page), limit: String(limit) });

        return httpClient.get<NotesPage>(`/api/clients/${clientId}/notes?${params}`);
    }

    addNote(clientId: number, content: string): Promise<Note> {
        return httpClient.post<Note>(`/api/clients/${clientId}/notes`, { content });
    }

    updateNote(noteId: number, content: string): Promise<Note> {
        return httpClient.patch<Note>(`/api/notes/${noteId}`, { content });
    }

    deleteNote(noteId: number): Promise<void> {
        return httpClient.del(`/api/notes/${noteId}`);
    }
}
