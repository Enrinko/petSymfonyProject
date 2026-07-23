import { httpClient } from './httpClient';

export interface Instrument {
    id: number;
    name: string;
    category: string;
    categoryLabel: string;
    sortOrder: number;
}

export interface InstrumentCategory {
    value: string;
    label: string;
}

export interface InstrumentCatalog {
    data: Instrument[];
    categories: InstrumentCategory[];
}

export interface InstrumentInput {
    name: string;
    category: string;
    sortOrder: number;
}

export class InstrumentApiService {
    getCatalog(): Promise<InstrumentCatalog> {
        return httpClient.get<InstrumentCatalog>('/api/instruments');
    }

    create(input: InstrumentInput): Promise<Instrument> {
        return httpClient.post<Instrument>('/api/admin/instruments', input);
    }

    update(id: number, input: InstrumentInput): Promise<Instrument> {
        return httpClient.patch<Instrument>(`/api/admin/instruments/${id}`, input);
    }

    remove(id: number): Promise<void> {
        return httpClient.del(`/api/admin/instruments/${id}`);
    }
}
