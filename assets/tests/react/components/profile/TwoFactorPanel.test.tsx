import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { describe, expect, it, vi } from 'vitest';
import TwoFactorPanel from '../../../../react/components/profile/TwoFactorPanel';
import { ProfileApiService } from '../../../../react/services/ProfileApiService';
import { server } from '../../../msw/server';

const SETUP = { secret: 'JBSWY3DPEHPK3PXP', otpauthUri: 'otpauth://totp/petSymphony:u@e.test?secret=JBSWY3DPEHPK3PXP' };
const CODES = ['0000000001', '0000000002', '0000000003', '0000000004', '0000000005', '0000000006', '0000000007', '0000000008'];

const setup = (enabled = false) => {
    const onChanged = vi.fn();
    render(<TwoFactorPanel api={new ProfileApiService()} enabled={enabled} onChanged={onChanged} />);
    return { onChanged };
};

describe('TwoFactorPanel', () => {
    it('мастер: setup → код → backup-коды один раз → onChanged', async () => {
        server.use(
            http.post('/api/profile/2fa/setup', () => HttpResponse.json(SETUP)),
            http.post('/api/profile/2fa/enable', () =>
                HttpResponse.json({ message: 'ok', backupCodes: CODES })),
        );
        const { onChanged } = setup();

        await userEvent.click(screen.getByRole('button', { name: 'Включить 2FA' }));

        expect(await screen.findByText(SETUP.secret)).toBeInTheDocument();

        await userEvent.type(screen.getByLabelText('Код подтверждения'), '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Включить' }));

        expect(await screen.findByText('0000000001')).toBeInTheDocument();
        expect(screen.getAllByRole('listitem')).toHaveLength(8);

        await userEvent.click(screen.getByRole('button', { name: 'Я сохранил коды' }));

        expect(onChanged).toHaveBeenCalled();
        expect(screen.queryByText('0000000001')).not.toBeInTheDocument();
    });

    it('неверный код при включении — ошибка у поля', async () => {
        server.use(
            http.post('/api/profile/2fa/setup', () => HttpResponse.json(SETUP)),
            http.post('/api/profile/2fa/enable', () =>
                HttpResponse.json({ message: 'Данные не прошли валидацию.', errors: { code: 'Неверный код.' } }, { status: 422 })),
        );
        setup();

        await userEvent.click(screen.getByRole('button', { name: 'Включить 2FA' }));
        await screen.findByText(SETUP.secret);
        await userEvent.type(screen.getByLabelText('Код подтверждения'), '000000');
        await userEvent.click(screen.getByRole('button', { name: 'Включить' }));

        expect(await screen.findByText('Неверный код.')).toBeInTheDocument();
    });

    it('отключение: пароль + код → onChanged', async () => {
        server.use(http.post('/api/profile/2fa/disable', () => HttpResponse.json({ message: 'ok' })));
        const { onChanged } = setup(true);

        await userEvent.click(screen.getByRole('button', { name: 'Отключить…' }));
        await userEvent.type(screen.getByLabelText('Текущий пароль'), 'password-1');
        await userEvent.type(screen.getByLabelText('Код (TOTP или резервный)'), '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Отключить 2FA' }));

        await waitFor(() => expect(onChanged).toHaveBeenCalled());
    });
});
