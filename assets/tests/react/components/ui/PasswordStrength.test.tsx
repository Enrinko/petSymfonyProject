import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PasswordStrength from '../../../../react/components/ui/PasswordStrength';

describe('PasswordStrength', () => {
    it('пустое значение не рендерится', () => {
        const { container } = render(<PasswordStrength value="" />);

        expect(container.firstChild).toBeNull();
    });

    it('короткий пароль — «слабый» (уровень 0)', () => {
        render(<PasswordStrength value="short" />);

        expect(screen.getByText('слабый')).toBeInTheDocument();
    });

    it('10 одинаковых букв — уровень 1', () => {
        render(<PasswordStrength value="aaaaaaaaaa" />);

        expect(screen.getByText('так себе')).toBeInTheDocument();
    });

    it('10+ символов с буквами и цифрами — уровень 2', () => {
        render(<PasswordStrength value="abcdefgh12" />);

        expect(screen.getByText('нормальный')).toBeInTheDocument();
    });

    // Значения — нарочно шаблонные (низкая энтропия): gitleaks в CI
    // принимает «реалистичные» тестовые пароли за утёкшие секреты
    it('14+ символов, буквы+цифры — уровень 3', () => {
        render(<PasswordStrength value="passwordpassword12" />);

        expect(screen.getByText('хороший')).toBeInTheDocument();
    });

    it('14+, буквы+цифры+спецсимвол или регистры — уровень 4', () => {
        render(<PasswordStrength value="Passwordpassword12!" />);

        expect(screen.getByText('отличный')).toBeInTheDocument();
    });

    it('подпись объявляется скринридеру (aria-live)', () => {
        render(<PasswordStrength value="whatever-pass" />);

        expect(screen.getByText(/слабый|так себе|нормальный|хороший|отличный/))
            .toHaveAttribute('aria-live', 'polite');
    });
});
