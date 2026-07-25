import { useEffect, useRef, useState } from 'react';
import QRCode from 'qrcode';
import { required } from '../../hooks/rules';
import { useForm } from '../../hooks/useForm';
import { ProfileApiService, TwoFactorSetup } from '../../services/ProfileApiService';
import { ApiError } from '../../services/httpClient';
import { playSound } from '../../utils/sound';
import { t } from '../../i18n';
import Alert from '../ui/Alert';
import Button from '../ui/Button';
import TextField from '../ui/TextField';

interface TwoFactorPanelProps {
    api: ProfileApiService;
    enabled: boolean;
    /** Родитель перезагружает профиль после включения/отключения. */
    onChanged: () => void;
}

type Step = 'status' | 'scan' | 'backup' | 'disable';

/**
 * Блок 2FA в секции «Безопасность»: статус → мастер включения
 * (QR → код подтверждения → backup-коды один раз) → отключение.
 */
export default function TwoFactorPanel({ api, enabled, onChanged }: TwoFactorPanelProps) {
    const [step, setStep] = useState<Step>('status');
    const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
    const [setupError, setSetupError] = useState<string | null>(null);
    const [backupCodes, setBackupCodes] = useState<string[]>([]);
    const [copied, setCopied] = useState(false);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    // QR рендерится на клиенте из otpauth-URI — секрет не гоняем картинкой
    useEffect(() => {
        if (step === 'scan' && setup && canvasRef.current) {
            QRCode.toCanvas(canvasRef.current, setup.otpauthUri, { width: 180, margin: 1 })
                .catch(() => { /* секрет виден текстом — QR не критичен */ });
        }
    }, [step, setup]);

    const confirm = useForm({
        initial: { code: '' },
        rules: { code: [required(t('frontend.profile.twofactor.code_required', 'Введите код из приложения.'))] },
        fallbackError: t('frontend.profile.twofactor.enable_failed', 'Не удалось включить 2FA.'),
        onSubmit: async (values) => {
            const result = await api.enable2fa(values.code.trim());
            playSound('success');
            setBackupCodes(result.backupCodes);
            setStep('backup');
        },
    });

    const disable = useForm({
        initial: { currentPassword: '', code: '' },
        rules: {
            currentPassword: [required()],
            code: [required(t('frontend.profile.twofactor.disable_code_required', 'Код из приложения или резервный.'))],
        },
        fallbackError: t('frontend.profile.twofactor.disable_failed', 'Не удалось отключить 2FA.'),
        onSubmit: async (values) => {
            await api.disable2fa(values.currentPassword, values.code.trim());
            playSound('notify');
            setStep('status');
            onChanged();
        },
    });

    const startSetup = async () => {
        setSetupError(null);

        try {
            setSetup(await api.setup2fa());
            confirm.reset();
            setStep('scan');
        } catch (err) {
            setSetupError(err instanceof ApiError ? err.message : t('frontend.profile.twofactor.setup_failed', 'Не удалось начать настройку 2FA.'));
        }
    };

    const finishBackup = () => {
        setStep('status');
        setBackupCodes([]);
        setCopied(false);
        onChanged();
    };

    const copyCodes = async () => {
        try {
            await navigator.clipboard.writeText(backupCodes.join('\n'));
            setCopied(true);
        } catch {
            setCopied(false); // нет прав на буфер — коды всё равно на экране
        }
    };

    if (step === 'scan' && setup) {
        return (
            <form onSubmit={confirm.handleSubmit} noValidate className="twofa">
                <p className="profile__field-hint">
                    {t('frontend.profile.twofactor.scan_hint', 'Отсканируйте QR-код приложением-аутентификатором (Google Authenticator, Aegis…) или введите секрет вручную, затем подтвердите шестизначным кодом.')}
                </p>
                <canvas ref={canvasRef} className="twofa__qr" aria-label={t('frontend.profile.twofactor.qr_aria_label', 'QR-код для приложения-аутентификатора')} />
                <p className="twofa__secret"><code>{setup.secret}</code></p>
                {confirm.errors.general && <Alert kind="error">{confirm.errors.general}</Alert>}
                <TextField
                    id="twofa-code"
                    label={t('frontend.profile.twofactor.code_label', 'Код подтверждения')}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder="123 456"
                    required
                    {...confirm.fieldProps('code')}
                />
                <div className="profile__actions">
                    <Button type="submit" loading={confirm.submitting}>{t('frontend.profile.twofactor.enable_button', 'Включить')}</Button>
                    <Button type="button" variant="ghost" onClick={() => setStep('status')}>{t('frontend.profile.twofactor.cancel', 'Отмена')}</Button>
                </div>
            </form>
        );
    }

    if (step === 'backup') {
        return (
            <div className="twofa">
                <Alert kind="success">{t('frontend.profile.twofactor.enabled_alert', 'Двухфакторная аутентификация включена.')}</Alert>
                <p className="profile__field-hint">
                    <strong>{t('frontend.profile.twofactor.backup_codes_strong', 'Резервные коды')}</strong>
                    {' '}
                    {t('frontend.profile.twofactor.backup_codes_hint', '— единственный способ войти без телефона. Сохраните их: показываются только один раз, каждый работает единожды.')}
                </p>
                <ul className="twofa__codes">
                    {backupCodes.map((code) => <li key={code}><code>{code}</code></li>)}
                </ul>
                <div className="profile__actions">
                    <Button type="button" variant="ghost" onClick={copyCodes}>
                        {copied ? t('frontend.profile.twofactor.copied', 'Скопировано ✓') : t('frontend.profile.twofactor.copy_all', 'Скопировать все')}
                    </Button>
                    <Button type="button" onClick={finishBackup}>{t('frontend.profile.twofactor.saved_codes', 'Я сохранил коды')}</Button>
                </div>
            </div>
        );
    }

    if (step === 'disable') {
        return (
            <form onSubmit={disable.handleSubmit} noValidate className="twofa">
                {disable.errors.general && <Alert kind="error">{disable.errors.general}</Alert>}
                <TextField
                    id="twofa-disable-password"
                    label={t('frontend.profile.twofactor.current_password_label', 'Текущий пароль')}
                    type="password"
                    autoComplete="current-password"
                    required
                    {...disable.fieldProps('currentPassword')}
                />
                <TextField
                    id="twofa-disable-code"
                    label={t('frontend.profile.twofactor.disable_code_label', 'Код (TOTP или резервный)')}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    required
                    {...disable.fieldProps('code')}
                />
                <div className="profile__actions">
                    <Button type="submit" variant="ghost" loading={disable.submitting}>{t('frontend.profile.twofactor.disable_button', 'Отключить 2FA')}</Button>
                    <Button type="button" variant="ghost" onClick={() => setStep('status')}>{t('frontend.profile.twofactor.cancel', 'Отмена')}</Button>
                </div>
            </form>
        );
    }

    return (
        <div className="twofa">
            {setupError && <Alert kind="error">{setupError}</Alert>}
            <p className="profile__field-hint">
                {enabled
                    ? t('frontend.profile.twofactor.status_enabled', 'Двухфакторная аутентификация включена: при входе нужен код из приложения.')
                    : t('frontend.profile.twofactor.status_disabled', 'Вторая ступень входа: код из приложения-аутентификатора. Рекомендуем всем, особенно администраторам.')}
            </p>
            <div className="profile__actions">
                {enabled
                    ? (
                        <Button type="button" variant="ghost" onClick={() => { disable.reset(); setStep('disable'); }}>
                            {t('frontend.profile.twofactor.disable_link', 'Отключить…')}
                        </Button>
                    )
                    : <Button type="button" onClick={startSetup}>{t('frontend.profile.twofactor.enable_2fa_button', 'Включить 2FA')}</Button>}
            </div>
        </div>
    );
}
