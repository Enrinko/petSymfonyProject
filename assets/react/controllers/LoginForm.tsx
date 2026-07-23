import { FormEvent, useState } from 'react';
import { AuthApiService } from '../services/AuthApiService';
import { ApiError } from '../services/httpClient';
import { isSoundEnabled, playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import Checkbox from '../components/ui/Checkbox';
import TextField from '../components/ui/TextField';

interface LoginFormProps {
    csrfToken: string;
    loginUrl: string;
    redirectUrl: string;
    /** Email REMEMBERED-пользователя, которого попросили подтвердить пароль. */
    prefillEmail?: string | null;
}

export default function LoginForm({ csrfToken, loginUrl, redirectUrl, prefillEmail }: LoginFormProps) {
    const [email, setEmail] = useState(prefillEmail ?? '');
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const apiService = new AuthApiService(loginUrl);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await apiService.login(email, password, csrfToken, rememberMe);
            // Камертон «ля» при входе; крошечная пауза, чтобы нота успела прозвучать
            if (isSoundEnabled()) {
                playSound('login');
                await new Promise((resolve) => setTimeout(resolve, 220));
            }
            window.location.href = redirectUrl;
            return;
        } catch (err) {
            playSound('error');
            // Сервер сам различает 401 «Неверный email или пароль» и 429 троттлинг.
            setError(err instanceof ApiError ? err.message : 'Не удалось войти. Попробуйте ещё раз.');
        }

        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit} noValidate>
            {error && <Alert kind="error" className="auth-form__alert">{error}</Alert>}

            <TextField
                id="login-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoFocus={!prefillEmail}
            />
            <TextField
                id="login-password"
                label="Пароль"
                type="password"
                autoComplete="current-password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                autoFocus={!!prefillEmail}
            />

            <div className="auth-form__remember">
                <Checkbox
                    id="login-remember"
                    label="Запомнить меня на 30 дней"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                />
            </div>

            <div className="auth-form__actions">
                <Button type="submit" loading={loading} block>
                    {loading ? 'Входим…' : 'Войти'}
                </Button>
            </div>
        </form>
    );
}
