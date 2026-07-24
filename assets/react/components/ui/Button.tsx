import { ButtonHTMLAttributes, ReactNode } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    loading?: boolean;
    variant?: 'primary' | 'brass' | 'ghost';
    size?: 'md' | 'sm';
    block?: boolean;
    children: ReactNode;
}

export default function Button({
    loading = false,
    variant = 'primary',
    size = 'md',
    block = false,
    disabled,
    children,
    ...buttonProps
}: ButtonProps) {
    const classes = [
        'btn',
        `btn--${variant}`,
        size === 'sm' ? 'btn--sm' : '',
        block ? 'btn--block' : '',
    ].filter(Boolean).join(' ');

    return (
        <button className={classes} disabled={disabled || loading} aria-busy={loading} {...buttonProps}>
            {loading && (
                <span className="metronome" aria-hidden="true">
                    <span /><span /><span />
                </span>
            )}
            {children}
        </button>
    );
}
