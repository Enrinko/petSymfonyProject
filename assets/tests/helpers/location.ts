import { vi } from 'vitest';

/**
 * jsdom не умеет навигацию: подменяем window.location целиком.
 * unstubGlobals: true в vitest.config.ts вернёт оригинал после каждого теста.
 */
export function stubLocation(pathname = '/'): { assign: ReturnType<typeof vi.fn> } {
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, pathname, assign });
    return { assign };
}
