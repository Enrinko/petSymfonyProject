/**
 * Единый HTTP-клиент фронтенда.
 *
 * Сквозные правила в одном месте: JSON-заголовки, X-Requested-With (защита
 * в глубину от CSRF), таймаут через AbortController, разбор конверта ошибок
 * API `{message, errors}` в типизированный ApiError, редирект на /login при
 * истёкшей сессии.
 */

export const NETWORK_ERROR_MESSAGE = 'Сервер недоступен. Проверьте соединение.';

const TIMEOUT_MS = 15_000;

export class ApiError extends Error {
    constructor(
        readonly status: number,
        message: string,
        readonly errors: Record<string, string> | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

interface RequestOptions {
    /** Не редиректить на /login при 401 (формы логина: 401 = неверный пароль). */
    skipAuthRedirect?: boolean;
    /** Внешний сигнал отмены (для гонки запросов, напр. поиск по мере ввода). */
    signal?: AbortSignal;
}

interface ErrorEnvelope {
    message?: string;
    errors?: Record<string, string> | null;
}

async function request<T>(method: string, url: string, body?: unknown, options?: RequestOptions): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

    // Внешняя отмена (гонка запросов) прокидывается во внутренний контроллер
    if (options?.signal) {
        if (options.signal.aborted) {
            controller.abort();
        } else {
            options.signal.addEventListener('abort', () => controller.abort(), { once: true });
        }
    }

    // FormData уходит как multipart: Content-Type проставит браузер (с boundary)
    const isForm = body instanceof FormData;

    let response: Response;

    try {
        response = await fetch(url, {
            method,
            mode: 'same-origin',
            headers: {
                ...(isForm ? {} : { 'Content-Type': 'application/json' }),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
            signal: controller.signal,
        });
    } catch {
        throw new ApiError(options?.signal?.aborted ? -1 : 0, NETWORK_ERROR_MESSAGE);
    } finally {
        clearTimeout(timer);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    let payload: unknown = null;

    try {
        payload = await response.json();
    } catch {
        // Не-JSON тело (например, HTML-страница ошибки) — оставляем null.
    }

    if (!response.ok) {
        if (
            response.status === 401
            && !options?.skipAuthRedirect
            && !window.location.pathname.startsWith('/login')
        ) {
            window.location.assign('/login?expired=1');
        }

        const envelope: ErrorEnvelope = typeof payload === 'object' && payload !== null ? payload : {};

        throw new ApiError(
            response.status,
            envelope.message ?? 'Не удалось выполнить запрос.',
            envelope.errors ?? null,
        );
    }

    return payload as T;
}

/** Запрос отменён внешним сигналом (не сетевая ошибка) — вызывающий может проглотить. */
export function isAborted(error: unknown): boolean {
    return error instanceof ApiError && error.status === -1;
}

export const httpClient = {
    get<T>(url: string, options?: RequestOptions): Promise<T> {
        return request<T>('GET', url, undefined, options);
    },
    post<T>(url: string, body?: unknown, options?: RequestOptions): Promise<T> {
        return request<T>('POST', url, body, options);
    },
    postForm<T>(url: string, form: FormData, options?: RequestOptions): Promise<T> {
        return request<T>('POST', url, form, options);
    },
    put<T>(url: string, body?: unknown, options?: RequestOptions): Promise<T> {
        return request<T>('PUT', url, body, options);
    },
    patch<T>(url: string, body?: unknown, options?: RequestOptions): Promise<T> {
        return request<T>('PATCH', url, body, options);
    },
    del<T = void>(url: string, options?: RequestOptions): Promise<T> {
        return request<T>('DELETE', url, undefined, options);
    },
};
