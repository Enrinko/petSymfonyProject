import { InputHTMLAttributes } from 'react';

interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
}

export default function Checkbox({ label, id, ...inputProps }: CheckboxProps) {
    return (
        <label className="checkbox" htmlFor={id}>
            <input id={id} type="checkbox" className="checkbox__input" {...inputProps} />
            <span className="checkbox__box" aria-hidden="true">✓</span>
            <span className="checkbox__label">{label}</span>
        </label>
    );
}
