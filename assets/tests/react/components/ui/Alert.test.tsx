import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Alert from '../../../../react/components/ui/Alert';

describe('Alert', () => {
    it('ошибка — ассертивный role="alert"', () => {
        render(<Alert kind="error">Всё упало</Alert>);

        expect(screen.getByRole('alert')).toHaveTextContent('Всё упало');
    });

    it('успех — вежливый role="status" (не перебивает скринридер)', () => {
        render(<Alert kind="success">Сохранено</Alert>);

        expect(screen.getByRole('status')).toHaveTextContent('Сохранено');
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});
