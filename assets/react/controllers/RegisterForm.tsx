import { FormEvent, useState } from 'react';
import { AuthApiService } from '../services/AuthApiService';
import { ApiError } from '../services/httpClient';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import TextField from '../components/ui/TextField';

interface RegisterFormProps {
    registerUrl: string;
    loginUrl: string;
}

export default function RegisterForm({ registerUrl, loginUrl }: RegisterFormProps) {
    const [fields, setFields] = useState({ email: '', password: '', passwordConfirm: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);
    const apiService = new AuthApiService(undefined, registerUrl);

    const setField = (name: keyof typeof fields) =>
        (e: FormEvent<HTMLInputElement>) => {
            const { value } = e.currentTarget;
            setFields((prev) => ({ ...prev, [name]: value }));
        };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            await apiService.register(fields);
            window.location.href = `${loginUrl}?registered=1`;
            return;
        } catch (err) {
            if (err instanceof ApiError) {
                setErrors(err.errors ?? { general: err.message });
            } else {
                setErrors({ general: 'Не удалось зарегистрироваться.' });
            }
        }

        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit} noValidate>
            {errors.general && <Alert kind="error" className="auth-form__alert">{errors.general}</Alert>}

            <TextField
                id="register-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={fields.email}
                onChange={setField('email')}
                error={errors.email}
                required
                autoFocus
            />
            <TextField
                id="register-password"
                label="Пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Минимум 10 символов"
                value={fields.password}
                onChange={setField('password')}
                error={errors.password}
                required
            />
            <TextField
                id="register-password-confirm"
                label="Повторите пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Ещё раз"
                value={fields.passwordConfirm}
                onChange={setField('passwordConfirm')}
                error={errors.passwordConfirm}
                required
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={loading} block>
                    {loading ? 'Создаём аккаунт…' : 'Создать аккаунт'}
                </Button>
            </div>
        </form>
    );
}
