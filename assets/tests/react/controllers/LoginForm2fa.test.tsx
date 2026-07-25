import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import LoginForm from '../../../react/controllers/LoginForm';
import { stubLocation } from '../../helpers/location';
import { server } from '../../msw/server';

const renderLogin = () =>
    render(<LoginForm csrfToken="t" loginUrl="/api/login" redirectUrl="/dashboard" />);

describe('LoginForm: шаг 2FA', () => {
    it('twoFactorRequired → шаг кода → верный код → редирект', async () => {
        server.use(
            http.post('/api/login', () => HttpResponse.json({ twoFactorRequired: true })),
            http.post('/2fa_check', () => HttpResponse.json({ message: 'ok' })),
        );
        stubLocation('/login');
        renderLogin();

        await userEvent.type(screen.getByLabelText('Email'), 'a@b.test');
        await userEvent.type(screen.getByLabelText('Пароль'), 'long-password-1');
        await userEvent.click(screen.getByRole('button', { name: 'Войти' }));

        // Шаг кода
        const codeField = await screen.findByLabelText('Код подтверждения');
        await userEvent.type(codeField, '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Подтвердить' }));

        await waitFor(() => expect(window.location.href).toBe('/dashboard'));
    });

    it('неверный код — ошибка, шаг не закрывается', async () => {
        server.use(
            http.post('/api/login', () => HttpResponse.json({ twoFactorRequired: true })),
            http.post('/2fa_check', () =>
                HttpResponse.json({ message: 'Неверный код. Попробуйте ещё раз или используйте резервный.', errors: null }, { status: 401 })),
        );
        stubLocation('/login');
        renderLogin();

        await userEvent.type(screen.getByLabelText('Email'), 'a@b.test');
        await userEvent.type(screen.getByLabelText('Пароль'), 'long-password-1');
        await userEvent.click(screen.getByRole('button', { name: 'Войти' }));

        await userEvent.type(await screen.findByLabelText('Код подтверждения'), '000000');
        await userEvent.click(screen.getByRole('button', { name: 'Подтвердить' }));

        expect(await screen.findByText(/Неверный код/)).toBeInTheDocument();
        expect(screen.getByLabelText('Код подтверждения')).toBeInTheDocument();
    });

    it('без 2FA — обычный вход без шага кода', async () => {
        server.use(http.post('/api/login', () =>
            HttpResponse.json({ user: { id: 1, email: 'a@b.test', roles: ['ROLE_USER'] } })));
        stubLocation('/login');
        renderLogin();

        await userEvent.type(screen.getByLabelText('Email'), 'a@b.test');
        await userEvent.type(screen.getByLabelText('Пароль'), 'long-password-1');
        await userEvent.click(screen.getByRole('button', { name: 'Войти' }));

        await waitFor(() => expect(window.location.href).toBe('/dashboard'));
        expect(screen.queryByLabelText('Код подтверждения')).not.toBeInTheDocument();
    });
});
