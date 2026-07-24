import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SearchPalette from '../../../react/controllers/SearchPalette';
import { SearchResults } from '../../../react/services/SearchApiService';
import { stubLocation } from '../../helpers/location';
import { server } from '../../msw/server';

const RESULTS: SearchResults = {
    clients: [{ id: 7, name: 'Анна Скрипкина', email: 'anna@s.test', phone: null, archived: false }],
    tags: [{ name: 'вокал', usageCount: 3 }],
    notes: [{ id: 5, clientId: 9, clientName: 'Пётр Клавишин', snippet: 'разучили этюд', createdAt: '2026-07-01T00:00:00+00:00' }],
};

/** Отдаёт результаты поиска и копит входящие запросы. */
const arrangeSearch = (results: SearchResults = RESULTS) => {
    const requests: string[] = [];
    server.use(http.get('/api/search', ({ request }) => {
        requests.push(new URL(request.url).searchParams.get('q') ?? '');
        return HttpResponse.json(results);
    }));
    return requests;
};

const openPalette = () => {
    fireEvent.keyDown(window, { code: 'KeyK', ctrlKey: true });
};

/** Ввод запроса и промотка debounce (200ms) на fake timers. */
const typeQuery = async (value: string) => {
    vi.useFakeTimers();
    fireEvent.change(screen.getByLabelText('Поисковый запрос'), { target: { value } });
    await vi.advanceTimersByTimeAsync(200);
    vi.useRealTimers();
};

describe('SearchPalette', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('закрыта по умолчанию, Ctrl+K открывает и закрывает', () => {
        render(<SearchPalette clientsBasePath="/clients" />);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        openPalette();
        expect(screen.getByRole('dialog', { name: 'Поиск' })).toBeInTheDocument();

        openPalette();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('короткий запрос — подсказка, сеть не трогаем', async () => {
        const requests = arrangeSearch();
        render(<SearchPalette clientsBasePath="/clients" />);
        openPalette();

        await typeQuery('a');

        expect(screen.getByText(/минимум 2 символа/)).toBeInTheDocument();
        expect(requests).toHaveLength(0);
    });

    it('после debounce рендерит группы результатов', async () => {
        const requests = arrangeSearch();
        render(<SearchPalette clientsBasePath="/clients" />);
        openPalette();

        await typeQuery('ан');

        expect(await screen.findByText('Анна Скрипкина')).toBeInTheDocument();
        expect(screen.getByText('Ученики')).toBeInTheDocument();
        expect(screen.getByText('Теги')).toBeInTheDocument();
        expect(screen.getByText('Заметки')).toBeInTheDocument();
        expect(screen.getByText(/3 ученика/)).toBeInTheDocument();
        expect(requests).toEqual(['ан']);
    });

    it('Enter по активному результату ведёт на карточку клиента', async () => {
        arrangeSearch();
        const { assign } = stubLocation();
        render(<SearchPalette clientsBasePath="/clients" />);
        openPalette();

        await typeQuery('ан');
        await screen.findByText('Анна Скрипкина');

        fireEvent.keyDown(screen.getByLabelText('Поисковый запрос'), { key: 'Enter' });

        expect(assign).toHaveBeenCalledWith('/clients/7');
    });

    it('клик по тегу ведёт на список с фильтром', async () => {
        arrangeSearch();
        const { assign } = stubLocation();
        render(<SearchPalette clientsBasePath="/clients" />);
        openPalette();

        await typeQuery('вок');
        await screen.findByText('вокал');

        await userEvent.click(screen.getByRole('button', { name: /вокал/ }));

        expect(assign).toHaveBeenCalledWith('/clients?tags=%D0%B2%D0%BE%D0%BA%D0%B0%D0%BB');
    });

    it('Escape закрывает и сбрасывает состояние', async () => {
        arrangeSearch();
        render(<SearchPalette clientsBasePath="/clients" />);
        openPalette();

        await typeQuery('ан');
        await screen.findByText('Анна Скрипкина');

        fireEvent.keyDown(screen.getByLabelText('Поисковый запрос'), { key: 'Escape' });

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        // Повторное открытие — с чистого листа
        openPalette();
        expect(screen.getByLabelText('Поисковый запрос')).toHaveValue('');
        expect(screen.queryByText('Анна Скрипкина')).not.toBeInTheDocument();
    });
});
