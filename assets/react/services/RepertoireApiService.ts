import { httpClient } from './httpClient';

export type PieceStatusValue = 'learning' | 'memorizing' | 'ready' | 'in_repertoire';

export interface RepertoirePiece {
    id: number;
    title: string;
    composer: string | null;
    status: PieceStatusValue;
    statusLabel: string;
    note: string | null;
    startedAt: string;
    canAdvance: boolean;
    canStepBack: boolean;
}

export class RepertoireApiService {
    getPieces(clientId: number): Promise<{ data: RepertoirePiece[] }> {
        return httpClient.get<{ data: RepertoirePiece[] }>(`/api/clients/${clientId}/repertoire`);
    }

    addPiece(clientId: number, title: string, composer: string | null): Promise<RepertoirePiece> {
        return httpClient.post<RepertoirePiece>(`/api/clients/${clientId}/repertoire`, { title, composer });
    }

    advance(pieceId: number): Promise<RepertoirePiece> {
        return httpClient.patch<RepertoirePiece>(`/api/repertoire/${pieceId}/advance`);
    }

    stepBack(pieceId: number): Promise<RepertoirePiece> {
        return httpClient.patch<RepertoirePiece>(`/api/repertoire/${pieceId}/back`);
    }

    updateNote(pieceId: number, note: string): Promise<RepertoirePiece> {
        return httpClient.patch<RepertoirePiece>(`/api/repertoire/${pieceId}/note`, { note });
    }

    remove(pieceId: number): Promise<void> {
        return httpClient.del(`/api/repertoire/${pieceId}`);
    }
}
