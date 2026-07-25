import { useEffect, useState } from 'react';
import { email, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { t } from '../i18n';
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
    const [twoFactorStep, setTwoFactorStep] = useState(false);
    const apiService = new AuthApiService(loginUrl);

    const finishLogin = async () => {
        if (isSoundEnabled()) {
            playSound('login');
            await new Promise((resolve) => setTimeout(resolve, 220));
        }
        window.location.href = redirectUrl;
    };

    const twoFactor = useForm({
        initial: { code: '' },
        rules: { code: [required(t('frontend.auth.2fa.code_required', 'Введите код из приложения или резервный.'))] },
        fallbackError: t('frontend.auth.2fa.fallback_error', 'Не удалось проверить код. Попробуйте ещё раз.'),
        onSubmit: async (values) => {
            await apiService.submitTwoFactorCode(values.code.trim());
            await finishLogin();
        },
    });

    const form = useForm({
        initial: { email: prefillEmail ?? '', password: '' },
        rules: {
            email: [required(), email()],
            password: [required()],
        },
        fallbackError: t('frontend.auth.login.fallback_error', 'Не удалось войти. Попробуйте ещё раз.'),
        onSubmit: async (values) => {
            // Сервер сам различает 401 «Неверный email или пароль» и 429 троттлинг
            const result = await apiService.login(values.email, values.password, csrfToken, rememberMe);

            // Включена 2FA: пароль принят, показываем шаг ввода кода
            if (result.twoFactorRequired) {
                twoFactor.reset();
                setTwoFactorStep(true);
                return;
            }

            await finishLogin();
        },
    });

    const hasErrors = Object.keys(form.errors).length > 0 || Object.keys(twoFactor.errors).length > 0;
    useEffect(() => {
        if (hasErrors) {
            playSound('error');
        }
    }, [hasErrors]);

    if (twoFactorStep) {
        return (
            <form onSubmit={twoFactor.handleSubmit} noValidate>
                {twoFactor.errors.general && <Alert kind="error" className="auth-form__alert">{twoFactor.errors.general}</Alert>}

                <p className="auth-form__sub">
                    {t('frontend.auth.2fa.prompt', 'Введите шестизначный код из приложения-аутентификатора или один из резервных кодов.')}
                </p>
                <TextField
                    id="login-2fa-code"
                    label={t('frontend.auth.2fa.code_label', 'Код подтверждения')}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder="123 456"
                    required
                    autoFocus
                    {...twoFactor.fieldProps('code')}
                />

                <div className="auth-form__actions">
                    <Button type="submit" loading={twoFactor.submitting} block>
                        {twoFactor.submitting
                            ? t('frontend.auth.2fa.submitting', 'Проверяем…')
                            : t('frontend.auth.2fa.submit', 'Подтвердить')}
                    </Button>
                </div>
            </form>
        );
    }

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
                label={t('frontend.auth.password_label', 'Пароль')}
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
                    label={t('frontend.auth.login.remember', 'Запомнить меня на 30 дней')}
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                />
            </div>

            <div className="auth-form__actions">
                <Button type="submit" loading={form.submitting} block>
                    {form.submitting
                        ? t('frontend.auth.login.submitting', 'Входим…')
                        : t('frontend.auth.login.submit', 'Войти')}
                </Button>
            </div>
        </form>
    );
}
