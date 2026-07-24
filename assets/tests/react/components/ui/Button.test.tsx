import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Button from '../../../../react/components/ui/Button';

describe('Button', () => {
    it('в loading-состоянии — disabled и aria-busy', () => {
        render(<Button loading>Сохраняем…</Button>);

        const button = screen.getByRole('button', { name: 'Сохраняем…' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
    });

    it('в обычном состоянии aria-busy=false', () => {
        render(<Button>Сохранить</Button>);

        expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'false');
    });
});
