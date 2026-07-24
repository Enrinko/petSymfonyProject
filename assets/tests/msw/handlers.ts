/**
 * Базовые msw-хендлеры по контракту docs/api/openapi.yaml.
 * Возвращают пустые страницы — конкретный тест уточняет ответ
 * через server.use(...) со своими данными.
 */
import { http, HttpResponse } from 'msw';

export const handlers = [
    http.get('/api/admin/users', () =>
        HttpResponse.json({ users: [], total: 0, page: 1, perPage: 20 })),
    http.get('/api/search', () =>
        HttpResponse.json({ clients: [], tags: [], notes: [] })),
];
