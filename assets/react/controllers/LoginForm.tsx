import { useEffect, useState } from 'react';
import { email, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { AuthApiService } from '../services/AuthApiService';
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
    const [rememberMe, setRememberMe] = useState(false);
    const apiService = new AuthApiService(loginUrl);

    const form = useForm({
        initial: { email: prefillEmail ?? '', password: '' },
        rules: {
            email: [required(), email()],
            password: [required()],
        },
        fallbackError: 'Не удалось войти. Попробуйте ещё раз.',
        onSubmit: async (values) => {
            // Сервер сам различает 401 «Неверный email или пароль» и 429 троттлинг
            await apiService.login(values.email, values.password, csrfToken, rememberMe);
            // Камертон «ля» при входе; крошечная пауза, чтобы нота успела прозвучать
            if (isSoundEnabled()) {
                playSound('login');
                await new Promise((resolve) => setTimeout(resolve, 220));
            }
            window.location.href = redirectUrl;
        },
    });

    const hasErrors = Object.keys(form.errors).length > 0;
    useEffect(() => {
        if (hasErrors) {
            playSound('error');
        }
    }, [hasErrors]);

    return (
        <form onSubmit={form.handleSubmit} noValidate>
            {form.errors.general && <Alert kind="error" className="auth-form__alert">{form.errors.general}</Alert>}

            <TextField
                id="login-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                required
                autoFocus={!prefillEmail}
                {...form.fieldProps('email')}
            />
            <TextField
                id="login-password"
                label="Пароль"
                type="password"
                autoComplete="current-password"
                placeholder="••••••••"
                required
                autoFocus={!!prefillEmail}
                {...form.fieldProps('password')}
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
                <Button type="submit" loading={form.submitting} block>
                    {form.submitting ? 'Входим…' : 'Войти'}
                </Button>
            </div>
        </form>
    );
}
