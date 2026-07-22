import { InputHTMLAttributes } from 'react';

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
}

export default function TextField({ label, error, id, ...inputProps }: TextFieldProps) {
    const describedBy = error && id ? `${id}-error` : undefined;

    return (
        <div className="field">
            <label className="field__label" htmlFor={id}>{label}</label>
            <input
                id={id}
                className={`field__input${error ? ' field__input--invalid' : ''}`}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                {...inputProps}
            />
            {error && <span className="field__error" id={describedBy}>{error}</span>}
        </div>
    );
}
