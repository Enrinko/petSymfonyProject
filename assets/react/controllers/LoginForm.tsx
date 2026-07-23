import { FormEvent, useState } from 'react';
import { AuthApiService } from '../services/AuthApiService';
import { ApiError } from '../services/httpClient';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import TextField from '../components/ui/TextField';

interface LoginFormProps {
    csrfToken: string;
    loginUrl: string;
    redirectUrl: string;
}

export default function LoginForm({ csrfToken, loginUrl, redirectUrl }: LoginFormProps) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const apiService = new AuthApiService(loginUrl);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await apiService.login(email, password, csrfToken);
            window.location.href = redirectUrl;
            return;
        } catch (err) {
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
                autoFocus
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
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={loading} block>
                    {loading ? 'Входим…' : 'Войти'}
                </Button>
            </div>
        </form>
    );
}
