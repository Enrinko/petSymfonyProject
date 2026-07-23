import { httpClient } from './httpClient';

export interface TagInfo {
    id: number;
    name: string;
    usageCount: number;
}

export class TagApiService {
    getTags(): Promise<{ data: TagInfo[] }> {
        return httpClient.get<{ data: TagInfo[] }>('/api/tags');
    }

    deleteTag(id: number): Promise<void> {
        return httpClient.del(`/api/admin/tags/${id}`);
    }
}
