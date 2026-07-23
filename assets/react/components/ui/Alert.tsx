import { ReactNode } from 'react';

interface AlertProps {
    kind: 'error' | 'success';
    children: ReactNode;
    className?: string;
}

export default function Alert({ kind, children, className }: AlertProps) {
    return (
        <div className={`alert alert--${kind}${className ? ` ${className}` : ''}`} role="alert">
            <span aria-hidden="true">{kind === 'error' ? '✕' : '✓'}</span>
            <div>{children}</div>
        </div>
    );
}
