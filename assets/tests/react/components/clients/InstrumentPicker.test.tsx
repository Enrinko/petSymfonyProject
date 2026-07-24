import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import InstrumentPicker from '../../../../react/components/clients/InstrumentPicker';
import { Instrument } from '../../../../react/services/InstrumentApiService';

const CATALOG: Instrument[] = [
    { id: 1, name: 'Фортепиано', category: 'keys', categoryLabel: 'Клавишные', sortOrder: 1 },
    { id: 2, name: 'Гитара', category: 'strings', categoryLabel: 'Струнные', sortOrder: 2 },
    { id: 3, name: 'Скрипка', category: 'strings', categoryLabel: 'Струнные', sortOrder: 3 },
];

const setup = (selected: number[] = []) => {
    const onChange = vi.fn();
    render(<InstrumentPicker label="Инструменты" catalog={CATALOG} selected={selected} onChange={onChange} />);
    return { onChange };
};

describe('InstrumentPicker', () => {
    it('группирует по категории в порядке справочника', () => {
        setup();

        const labels = screen.getAllByText(/Клавишные|Струнные/).map((el) => el.textContent);

        expect(labels[0]).toContain('Клавишные');
        expect(labels[1]).toContain('Струнные');
        expect(screen.getAllByRole('button')).toHaveLength(3);
    });

    it('клик добавляет инструмент к выбранным', async () => {
        const { onChange } = setup([1]);

        await userEvent.click(screen.getByRole('button', { name: /Гитара/ }));

        expect(onChange).toHaveBeenCalledWith([1, 2]);
    });

    it('повторный клик снимает выбор', async () => {
        const { onChange } = setup([1, 2]);

        await userEvent.click(screen.getByRole('button', { name: /Гитара/ }));

        expect(onChange).toHaveBeenCalledWith([1]);
    });

    it('выбранные помечены aria-pressed', () => {
        setup([3]);

        expect(screen.getByRole('button', { name: /Скрипка/ })).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('button', { name: /Гитара/ })).toHaveAttribute('aria-pressed', 'false');
    });

    it('пустой каталог не рендерит ничего', () => {
        const { container } = render(
            <InstrumentPicker label="Инструменты" catalog={[]} selected={[]} onChange={vi.fn()} />,
        );

        expect(container.firstChild).toBeNull();
    });
});
