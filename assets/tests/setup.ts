/**
 * Общий сетап тестов: матчеры jest-dom и msw-сервер.
 * Неперехваченный запрос — ошибка: тест обязан объявить свои хендлеры.
 */
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';
import { server } from './msw/server';

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));

// При globals: false RTL не видит afterEach фреймворка и НЕ регистрирует
// auto-cleanup сам — без этой строки DOM тестов копится и getBy* находит
// элементы из уже отработавших тестов
afterEach(() => {
    cleanup();
    server.resetHandlers();
});

afterAll(() => server.close());
