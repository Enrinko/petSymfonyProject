import { delay, http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, httpClient, isAborted, NETWORK_ERROR_MESSAGE } from '../../../react/services/httpClient';
import { stubLocation } from '../../helpers/location';
import { server } from '../../msw/server';

describe('httpClient', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('разбирает конверт ошибки в типизированный ApiError', async () => {
        server.use(http.post('/api/clients', () =>
            HttpResponse.json({ message: 'Проверьте поля.', errors: { name: 'Обязательно' } }, { status: 422 })));
        stubLocation();

        const error = await httpClient.post('/api/clients', {}).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).status).toBe(422);
        expect((error as ApiError).message).toBe('Проверьте поля.');
        expect((error as ApiError).errors).toEqual({ name: 'Обязательно' });
    });

    it('204 → undefined', async () => {
        server.use(http.delete('/api/clients/1', () => new HttpResponse(null, { status: 204 })));

        await expect(httpClient.del('/api/clients/1')).resolves.toBeUndefined();
    });

    it('не-JSON тело ошибки → дефолтное сообщение', async () => {
        server.use(http.get('/api/boom', () =>
            new HttpResponse('<html>fatal</html>', { status: 500, headers: { 'Content-Type': 'text/html' } })));
        stubLocation();

        const error = await httpClient.get('/api/boom').catch((e: unknown) => e);

        expect((error as ApiError).status).toBe(500);
        expect((error as ApiError).message).toBe('Не удалось выполнить запрос.');
    });

    it('401 редиректит на /login?expired=1', async () => {
        server.use(http.get('/api/me', () => HttpResponse.json({ message: 'Unauthorized' }, { status: 401 })));
        const { assign } = stubLocation('/clients');

        await httpClient.get('/api/me').catch(() => undefined);

        expect(assign).toHaveBeenCalledWith('/login?expired=1');
    });

    it('401 без редиректа при skipAuthRedirect (форма логина)', async () => {
        server.use(http.post('/api/login', () => HttpResponse.json({ message: 'Bad credentials' }, { status: 401 })));
        const { assign } = stubLocation('/login');

        await httpClient.post('/api/login', {}, { skipAuthRedirect: true }).catch(() => undefined);

        expect(assign).not.toHaveBeenCalled();
    });

    it('таймаут → сетевая ошибка со статусом 0', async () => {
        vi.useFakeTimers();
        server.use(http.get('/api/slow', async () => {
            await delay('infinite');
            return HttpResponse.json({});
        }));
        stubLocation();

        const pending = httpClient.get('/api/slow').catch((e: unknown) => e);
        await vi.advanceTimersByTimeAsync(15_001);
        const error = await pending;

        expect((error as ApiError).status).toBe(0);
        expect((error as ApiError).message).toBe(NETWORK_ERROR_MESSAGE);
        expect(isAborted(error)).toBe(false);
    });

    it('внешний abort различим через isAborted (гонка поиска)', async () => {
        server.use(http.get('/api/search', async () => {
            await delay('infinite');
            return HttpResponse.json({});
        }));
        stubLocation();

        const controller = new AbortController();
        const pending = httpClient.get('/api/search', { signal: controller.signal }).catch((e: unknown) => e);
        controller.abort();
        const error = await pending;

        expect((error as ApiError).status).toBe(-1);
        expect(isAborted(error)).toBe(true);
    });
});
