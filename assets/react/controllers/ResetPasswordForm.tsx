import { useEffect, useState } from 'react';
import { matches, minLength, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { t } from '../i18n';
import { PasswordResetApiService } from '../services/PasswordResetApiService';
import { ApiError } from '../services/httpClient';
import { playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import PasswordStrength from '../components/ui/PasswordStrength';
import SuccessCheck from '../components/ui/SuccessCheck';
import TextField from '../components/ui/TextField';

interface ResetPasswordFormProps {
    token: string;
    loginUrl: string;
    forgotPasswordUrl: string;
}

type Outcome = 'idle' | 'success' | 'invalid_token';

export default function ResetPasswordForm({ token, loginUrl, forgotPasswordUrl }: ResetPasswordFormProps) {
    const [outcome, setOutcome] = useState<Outcome>('idle');
    const apiService = new PasswordResetApiService();

    const form = useForm({
        initial: { password: '', passwordConfirm: '' },
        rules: {
            password: [required(), minLength(10)],
            passwordConfirm: [required(), matches('password')],
        },
        fallbackError: t('frontend.auth.reset.fallback_error', 'Ошибка сервера. Попробуйте ещё раз.'),
        onSubmit: async (values) => {
            try {
                await apiService.performReset(token, values.password, values.passwordConfirm);
            } catch (err) {
                // Просроченный/битый токен — не ошибка поля, а отдельный экран
                if (err instanceof ApiError && err.status === 400) {
                    playSound('error');
                    setOutcome('invalid_token');
                    return;
                }

                throw err; // остальное — в form.errors обычным путём
            }

            playSound('success');
            setOutcome('success');
            setTimeout(() => { window.location.href = loginUrl; }, 2500);
        },
    });

    const hasErrors = Object.keys(form.errors).length > 0;
    useEffect(() => {
        if (hasErrors) {
            playSound('error');
        }
    }, [hasErrors]);

    if (outcome === 'success') {
        return (
            <div className="auth-form__done">
                <SuccessCheck />
                <h2 className="auth-form__title">{t('frontend.auth.reset.done_title', 'Пароль изменён')}</h2>
                <p className="auth-form__sub">{t('frontend.auth.reset.done_text', 'Перенаправляем на страницу входа…')}</p>
            </div>
        );
    }

    if (outcome === 'invalid_token') {
        return (
            <div className="auth-form__done">
                <Alert kind="error" className="auth-form__alert">
                    {t('frontend.auth.reset.invalid_token', 'Ссылка недействительна или истекла. Запросите новую.')}
                </Alert>
                <Button type="button" variant="ghost" block onClick={() => { window.location.href = forgotPasswordUrl; }}>
                    {t('frontend.auth.reset.request_new', 'Запросить новую ссылку')}
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={form.handleSubmit} noValidate>
            {form.errors.general && <Alert kind="error" className="auth-form__alert">{form.errors.general}</Alert>}

            <TextField
                id="reset-password"
                label={t('frontend.auth.reset.new_password_label', 'Новый пароль')}
                type="password"
                autoComplete="new-password"
                placeholder={t('frontend.auth.password_placeholder', 'Минимум 10 символов')}
                required
                {...form.fieldProps('password')}
            />
            <PasswordStrength value={form.values.password} />
            <TextField
                id="reset-password-confirm"
                label={t('frontend.auth.password_confirm_label', 'Повторите пароль')}
                type="password"
                autoComplete="new-password"
                placeholder={t('frontend.auth.password_repeat_placeholder', 'Ещё раз')}
                required
                {...form.fieldProps('passwordConfirm')}
            />

            <div className="auth-form__actions">
                <Button type="submit" loading={form.submitting} block>
                    {form.submitting
                        ? t('frontend.auth.reset.submitting', 'Сохраняем…')
                        : t('frontend.auth.reset.submit', 'Сохранить пароль')}
                </Button>
            </div>
        </form>
    );
}
