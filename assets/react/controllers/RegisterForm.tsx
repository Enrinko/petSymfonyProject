import { useEffect } from 'react';
import { email, matches, minLength, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { AuthApiService } from '../services/AuthApiService';
import { isSoundEnabled, playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import PasswordStrength from '../components/ui/PasswordStrength';
import TextField from '../components/ui/TextField';

interface RegisterFormProps {
    registerUrl: string;
    loginUrl: string;
}

export default function RegisterForm({ registerUrl, loginUrl }: RegisterFormProps) {
    const apiService = new AuthApiService(undefined, registerUrl);

    const form = useForm({
        initial: { email: '', password: '', passwordConfirm: '' },
        rules: {
            email: [required(), email()],
            password: [required(), minLength(10)],
            passwordConfirm: [required(), matches('password')],
        },
        fallbackError: 'Не удалось зарегистрироваться.',
        onSubmit: async (values) => {
            await apiService.register(values);
            if (isSoundEnabled()) {
                playSound('success');
                await new Promise((resolve) => setTimeout(resolve, 220));
            }
            window.location.href = `${loginUrl}?registered=1`;
        },
    });

    // Звук ошибки — на появление ошибок (throttle в sound.ts не даст «пулемёта»)
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
                id="register-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                required
                {...form.fieldProps('email')}
            />
            <TextField
                id="register-password"
                label="Пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Минимум 10 символов"
                required
                {...form.fieldProps('password')}
            />
            <PasswordStrength value={form.values.password} />
            <TextField
                id="register-password-confirm"
                label="Повторите пароль"
                type="password"
                autoComplete="new-password"
                placeholder="Ещё раз"
                required
                {...form.fieldProps('passwordConfirm')}
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={form.submitting} block>
                    {form.submitting ? 'Создаём аккаунт…' : 'Создать аккаунт'}
                </Button>
            </div>
        </form>
    );
}
