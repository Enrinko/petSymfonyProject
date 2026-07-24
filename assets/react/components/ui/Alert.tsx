import { ReactNode } from 'react';

interface AlertProps {
    kind: 'error' | 'success';
    children: ReactNode;
    className?: string;
}

export default function Alert({ kind, children, className }: AlertProps) {
    // role="alert" (ассертивный) — только для ошибок: успех не должен
    // перебивать скринридеру текущий ввод. Канон совпадает с Twig-алертом.
    return (
        <div
            className={`alert alert--${kind}${className ? ` ${className}` : ''}`}
            role={kind === 'error' ? 'alert' : 'status'}
        >
            <span aria-hidden="true">{kind === 'error' ? '✕' : '✓'}</span>
            <div>{children}</div>
        </div>
    );
}
