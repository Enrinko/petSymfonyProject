import { useEffect, useState } from 'react';
import { email, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { PasswordResetApiService } from '../services/PasswordResetApiService';
import { playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import SuccessCheck from '../components/ui/SuccessCheck';
import TextField from '../components/ui/TextField';

export default function ForgotPasswordForm() {
    const [sent, setSent] = useState(false);
    const apiService = new PasswordResetApiService();

    const form = useForm({
        initial: { email: '' },
        rules: { email: [required(), email()] },
        fallbackError: 'Не удалось отправить письмо. Попробуйте ещё раз.',
        onSubmit: async (values) => {
            // Ответ одинаковый для любого email — существование аккаунта не раскрывается.
            await apiService.requestReset(values.email);
            playSound('success');
            setSent(true);
        },
    });

    const hasErrors = Object.keys(form.errors).length > 0;
    useEffect(() => {
        if (hasErrors) {
            playSound('error');
        }
    }, [hasErrors]);

    if (sent) {
        return (
            <div className="auth-form__done">
                <SuccessCheck />
                <h2 className="auth-form__title">Проверьте почту</h2>
                <p className="auth-form__sub">
                    Если адрес <strong>{form.values.email}</strong> зарегистрирован, мы отправили
                    на него письмо со ссылкой для сброса пароля. Ссылка действует 1 час.
                </p>
            </div>
        );
    }

    return (
        <form onSubmit={form.handleSubmit} noValidate>
            {form.errors.general && (
                <Alert kind="error" className="auth-form__alert">
                    {form.errors.general}
                </Alert>
            )}

            <TextField
                id="forgot-email"
                label="Email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                required
                {...form.fieldProps('email')}
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={form.submitting} block>
                    {form.submitting ? 'Отправляем…' : 'Отправить ссылку'}
                </Button>
            </div>
        </form>
    );
}
