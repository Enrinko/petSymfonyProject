import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import TagInput from '../../../../react/components/clients/TagInput';

const setup = (value: string[] = []) => {
    const onChange = vi.fn();
    render(<TagInput id="tags" label="Теги" value={value} onChange={onChange} suggestions={['вокал']} />);
    return { onChange, input: screen.getByLabelText('Теги') };
};

describe('TagInput', () => {
    it('Enter добавляет нормализованный тег (trim + lowercase)', async () => {
        const { onChange, input } = setup();

        await userEvent.type(input, '  ВоКал  {Enter}');

        expect(onChange).toHaveBeenCalledWith(['вокал']);
    });

    it('запятая добавляет тег', async () => {
        const { onChange, input } = setup();

        await userEvent.type(input, 'гитара,');

        expect(onChange).toHaveBeenCalledWith(['гитара']);
    });

    it('дубль не добавляется', async () => {
        const { onChange, input } = setup(['вокал']);

        await userEvent.type(input, 'вокал{Enter}');

        expect(onChange).not.toHaveBeenCalled();
    });

    it('Backspace на пустом поле снимает последний тег', async () => {
        const { onChange, input } = setup(['вокал', 'гитара']);

        await userEvent.type(input, '{Backspace}');

        expect(onChange).toHaveBeenCalledWith(['вокал']);
    });

    it('крестик убирает конкретный тег', async () => {
        const { onChange } = setup(['вокал', 'гитара']);

        await userEvent.click(screen.getByRole('button', { name: 'Убрать тег вокал' }));

        expect(onChange).toHaveBeenCalledWith(['гитара']);
    });

    it('blur добавляет черновик', async () => {
        const { onChange, input } = setup();

        await userEvent.type(input, 'скрипка');
        await userEvent.tab();

        expect(onChange).toHaveBeenCalledWith(['скрипка']);
    });
});
