import { FormEvent, useState } from 'react';
import { PasswordResetApiService } from '../services/PasswordResetApiService';
import { ApiError } from '../services/httpClient';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import SuccessCheck from '../components/ui/SuccessCheck';
import TextField from '../components/ui/TextField';

interface ResetPasswordFormProps {
    token: string;
    loginUrl: string;
    forgotPasswordUrl: string;
}

type Status = 'idle' | 'loading' | 'success' | 'invalid_token';

export default function ResetPasswordForm({ token, loginUrl, forgotPasswordUrl }: ResetPasswordFormProps) {
    const [password, setPassword] = useState('');
    const [passwordConfirm, setPasswordConfirm] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [status, setStatus] = useState<Status>('idle');
    const apiService = new PasswordResetApiService();

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setStatus('loading');
        setErrors({});

        try {
            await apiService.performReset(token, password, passwordConfirm);
            setStatus('success');
            setTimeout(() => { window.location.href = loginUrl; }, 2500);
            return;
        } catch (err) {
            if (err instanceof ApiError && err.status === 400) {
                setStatus('invalid_token');
                return;
            }

            if (err instanceof ApiError) {
                setErrors(err.errors ?? { general: err.message });
            } else {
                setErrors({ general: 'Ошибка сервера. Попробуйте ещё раз.' });
            }
        }

        setStatus('idle');
    };

    if (status === 'success') {
        return (
            <div className="auth-form__done">
                <SuccessCheck />
                <h2 className="auth-form__title">Пароль изменён</h2>
                <p className="auth-form__sub">Перенаправляем на страницу входа…</p>
            </div>
        );
    }

    if (status === 'invalid_token') {
        return (
            <div className="auth-form__done">
                <Alert kind="error" className="auth-form__alert">
                    Ссылка недействительна или истекла. Запросите новую.
                </Alert>
                <Button type="button" variant="ghost" block onClick={() => { window.location.href = forgotPasswordUrl; }}>
                    Запросить новую ссылку
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} noValidate>
            {errors.general && <Alert kind="error" className="auth-form__alert">{errors.general}</Alert>}

            <TextField
                id="reset-password"
                label="Новый пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Минимум 10 символов"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                error={errors.password}
                required
                autoFocus
            />
            <TextField
                id="reset-password-confirm"
                label="Повторите пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Ещё раз"
                value={passwordConfirm}
                onChange={(e) => setPasswordConfirm(e.target.value)}
                error={errors.passwordConfirm}
                required
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={status === 'loading'} block>
                    {status === 'loading' ? 'Сохраняем…' : 'Сохранить пароль'}
                </Button>
            </div>
        </form>
    );
}
