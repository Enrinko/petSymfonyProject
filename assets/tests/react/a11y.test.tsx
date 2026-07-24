/**
 * Axe-smoke: автоматическая проверка ключевых компонентов на нарушения
 * WCAG (роли, лейблы, контраст в jsdom не проверяется). Страницы за
 * логином CLI-прогоном не охватить — их закрывают эти тесты.
 */
import { fireEvent, render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import Alert from '../../react/components/ui/Alert';
import TextField from '../../react/components/ui/TextField';
import InstrumentPicker from '../../react/components/clients/InstrumentPicker';
import LoginForm from '../../react/controllers/LoginForm';
import SearchPalette from '../../react/controllers/SearchPalette';

describe('a11y (axe)', () => {
    it('LoginForm без нарушений', async () => {
        const { container } = render(
            <LoginForm csrfToken="t" loginUrl="/api/login" redirectUrl="/" />,
        );

        expect(await axe(container)).toHaveNoViolations();
    });

    it('Alert (обе роли) без нарушений', async () => {
        const { container } = render(
            <>
                <Alert kind="error">Ошибка</Alert>
                <Alert kind="success">Успех</Alert>
            </>,
        );

        expect(await axe(container)).toHaveNoViolations();
    });

    it('TextField с ошибкой без нарушений', async () => {
        const { container } = render(
            <TextField id="f" label="Поле" value="" onChange={() => undefined} error="Обязательное поле" />,
        );

        expect(await axe(container)).toHaveNoViolations();
    });

    it('InstrumentPicker без нарушений', async () => {
        const { container } = render(
            <InstrumentPicker
                label="Инструменты"
                catalog={[{ id: 1, name: 'Гитара', category: 'strings', categoryLabel: 'Струнные', sortOrder: 1 }]}
                selected={[]}
                onChange={vi.fn()}
            />,
        );

        expect(await axe(container)).toHaveNoViolations();
    });

    it('открытая SearchPalette без нарушений', async () => {
        const { container } = render(<SearchPalette clientsBasePath="/clients" />);
        fireEvent.keyDown(window, { code: 'KeyK', ctrlKey: true });
        screen.getByRole('dialog', { name: 'Поиск' });

        expect(await axe(container)).toHaveNoViolations();
    });
});
