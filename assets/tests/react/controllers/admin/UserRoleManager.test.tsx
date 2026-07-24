import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import UserRoleManager from '../../../../react/controllers/admin/UserRoleManager';
import { AdminUser } from '../../../../react/services/RbacApiService';
import { server } from '../../../msw/server';

const user = (id: number, email: string, roles: AdminUser['roles'] = ['ROLE_USER']): AdminUser => ({
    id,
    email,
    roles,
    isActive: true,
    createdAt: '2026-07-01T00:00:00+00:00',
    deactivatedAt: null,
});

/** Отдаёт страницу пользователей и копит параметры входящих запросов. */
const arrangeUsers = (users: AdminUser[], total = users.length, perPage = 20) => {
    const requests: URLSearchParams[] = [];
    server.use(http.get('/api/admin/users', ({ request }) => {
        requests.push(new URL(request.url).searchParams);
        return HttpResponse.json({ users, total, page: 1, perPage });
    }));
    return requests;
};

describe('UserRoleManager', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('рендерит пользователей после загрузки', async () => {
        arrangeUsers([user(1, 'a@b.test'), user(2, 'c@d.test')]);

        const { container } = render(<UserRoleManager currentUserId={1} />);

        expect(await screen.findByText('a@b.test')).toBeInTheDocument();
        expect(screen.getByText('c@d.test')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument(); // Всего

        // a11y: загрузка завершена — aria-busy снят, live-регион объявил итог
        expect(container.querySelector('.users')).toHaveAttribute('aria-busy', 'false');
        expect(screen.getByText('Найдено пользователей: 2')).toHaveAttribute('aria-live', 'polite');
    });

    it('поиск дебаунсится: запрос уходит один раз спустя 300ms', async () => {
        const requests = arrangeUsers([user(1, 'a@b.test')]);

        // Начальная загрузка — на реальных таймерах: fake timers до render
        // подвесили бы и её, и внутренности waitFor
        render(<UserRoleManager currentUserId={1} />);
        await screen.findByText('a@b.test');

        // fireEvent.change вместо userEvent.type: user-event зависает в связке
        // с fake timers (его внутренние setTimeout-паузы), а для проверки
        // debounce реализм клавиатуры не нужен — важен только таймер
        vi.useFakeTimers();
        fireEvent.change(screen.getByLabelText('Поиск по email'), { target: { value: 'abc' } });
        expect(requests.filter((p) => p.get('search') === 'abc')).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(300);
        vi.useRealTimers(); // fetch уже ушёл; ответ ждём на реальных таймерах

        await waitFor(() =>
            expect(requests.filter((p) => p.get('search') === 'abc')).toHaveLength(1));
    });

    it('переключение роли создаёт черновик, сохранение шлёт PATCH и чистит его', async () => {
        arrangeUsers([user(1, 'admin@s.test', ['ROLE_USER', 'ROLE_ADMIN']), user(2, 'plain@s.test')]);
        let patched: unknown = null;
        server.use(http.patch('/api/admin/users/2/roles', async ({ request }) => {
            patched = await request.json();
            return HttpResponse.json(user(2, 'plain@s.test', ['ROLE_USER', 'ROLE_MODERATOR']));
        }));

        render(<UserRoleManager currentUserId={1} />);
        await screen.findByText('plain@s.test');

        // До переключения кнопки «Сохранить» нет
        expect(screen.queryByRole('button', { name: 'Сохранить' })).not.toBeInTheDocument();

        const row = screen.getByText('plain@s.test').closest('tr') as HTMLElement;
        await userEvent.click(within(row).getByRole('button', { name: /MODERATOR/ }));

        const save = await screen.findByRole('button', { name: 'Сохранить' });
        await userEvent.click(save);

        await waitFor(() => expect(patched).toEqual({ roles: ['ROLE_USER', 'ROLE_MODERATOR'] }));
        await waitFor(() =>
            expect(screen.queryByRole('button', { name: 'Сохранить' })).not.toBeInTheDocument());
    });

    it('USER заблокирован всегда, свой ADMIN — тоже', async () => {
        arrangeUsers([user(1, 'me@s.test', ['ROLE_USER', 'ROLE_ADMIN'])]);

        render(<UserRoleManager currentUserId={1} />);
        await screen.findByText('me@s.test');

        expect(screen.getByText('это вы')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /USER/ })).toBeDisabled();
        expect(screen.getByRole('button', { name: /ADMIN/ })).toBeDisabled();
        expect(screen.getByRole('button', { name: /MODERATOR/ })).toBeEnabled();
    });

    it('пагинация: total=45 → «1 / 3», клик вперёд запрашивает page=2', async () => {
        const requests = arrangeUsers([user(1, 'a@b.test')], 45);

        render(<UserRoleManager currentUserId={99} />);
        await screen.findByText('a@b.test');

        expect(screen.getByText('1 / 3')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Предыдущая страница' })).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: 'Следующая страница' }));

        await waitFor(() =>
            expect(requests.filter((p) => p.get('page') === '2')).toHaveLength(1));
    });
});
