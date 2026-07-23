import { FormEvent, useState } from 'react';
import { PasswordResetApiService } from '../services/PasswordResetApiService';
import { ApiError } from '../services/httpClient';
import { playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import SuccessCheck from '../components/ui/SuccessCheck';
import TextField from '../components/ui/TextField';

type Status = 'idle' | 'loading' | 'sent' | 'error';

export default function ForgotPasswordForm() {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<Status>('idle');
    const [error, setError] = useState<string | null>(null);
    const apiService = new PasswordResetApiService();

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setStatus('loading');
        setError(null);

        try {
            // Ответ одинаковый для любого email — существование аккаунта не раскрывается.
            await apiService.requestReset(email);
            playSound('success');
            setStatus('sent');
        } catch (err) {
            playSound('error');
            setError(err instanceof ApiError ? err.message : 'Не удалось отправить письмо. Попробуйте ещё раз.');
            setStatus('error');
        }
    };

    if (status === 'sent') {
        return (
            <div className="auth-form__done">
                <SuccessCheck />
                <h2 className="auth-form__title">Проверьте почту</h2>
                <p className="auth-form__sub">
                    Если адрес <strong>{email}</strong> зарегистрирован, мы отправили
                    на него письмо со ссылкой для сброса пароля. Ссылка действует 1 час.
                </p>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} noValidate>
            {status === 'error' && (
                <Alert kind="error" className="auth-form__alert">
                    {error ?? 'Не удалось отправить письмо. Попробуйте ещё раз.'}
                </Alert>
            )}

            <TextField
                id="forgot-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoFocus
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={status === 'loading'} block>
                    {status === 'loading' ? 'Отправляем…' : 'Отправить ссылку'}
                </Button>
            </div>
        </form>
    );
}
