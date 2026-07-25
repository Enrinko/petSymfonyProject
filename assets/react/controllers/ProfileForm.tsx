import { ChangeEvent, FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { matches, minLength, required } from '../hooks/rules';
import { useForm } from '../hooks/useForm';
import { t } from '../i18n';
import { uiLocale } from '../utils/locale';
import { Profile, ProfileApiService } from '../services/ProfileApiService';
import { ApiError } from '../services/httpClient';
import { playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import PasswordStrength from '../components/ui/PasswordStrength';
import TextField from '../components/ui/TextField';
import SessionsPanel from '../components/profile/SessionsPanel';
import TwoFactorPanel from '../components/profile/TwoFactorPanel';

const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

type FieldErrors = Record<string, string>;

export default function ProfileForm() {
    const api = useMemo(() => new ProfileApiService(), []);

    const [profile, setProfile] = useState<Profile | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    // Секция «Профиль»
    const [displayName, setDisplayName] = useState('');
    const [savingName, setSavingName] = useState(false);
    const [nameMessage, setNameMessage] = useState<string | null>(null);
    const [nameErrors, setNameErrors] = useState<FieldErrors>({});

    // Аватар
    const fileInput = useRef<HTMLInputElement>(null);
    const [avatarBusy, setAvatarBusy] = useState(false);
    const [avatarError, setAvatarError] = useState<string | null>(null);

    // Язык интерфейса: после сохранения — reload, чтобы сервер перерисовал всё в новой локали
    const [localeBusy, setLocaleBusy] = useState(false);
    const [localeError, setLocaleError] = useState<string | null>(null);

    // Секция «Безопасность» — на общем каркасе форм
    const [passwordSaved, setPasswordSaved] = useState(false);

    const security = useForm({
        initial: { currentPassword: '', newPassword: '', newPasswordConfirm: '' },
        rules: {
            currentPassword: [required()],
            newPassword: [required(), minLength(10)],
            newPasswordConfirm: [required(), matches('newPassword')],
        },
        fallbackError: 'Не удалось изменить пароль.',
        onSubmit: async (values) => {
            await api.changePassword(values.currentPassword, values.newPassword, values.newPasswordConfirm);
            playSound('success');
            setPasswordSaved(true);
        },
    });

    const securityHasErrors = Object.keys(security.errors).length > 0;
    useEffect(() => {
        if (securityHasErrors) {
            playSound('error');
            setPasswordSaved(false);
        }
    }, [securityHasErrors]);

    // После успеха — очистить поля паролей (сообщение остаётся)
    useEffect(() => {
        if (passwordSaved) {
            security.reset();
        }
        // security пересоздаётся каждый рендер — в deps нельзя (зацикливание)
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [passwordSaved]);

    const loadProfile = () => {
        api.getProfile()
            .then((data) => {
                setProfile(data);
                setDisplayName(data.displayName ?? '');
            })
            .catch((err) => {
                setLoadError(err instanceof ApiError ? err.message : 'Не удалось загрузить профиль.');
            });
    };

    useEffect(() => {
        loadProfile();
        // загрузка один раз при монтировании; loadProfile пересоздаётся каждый рендер
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [api]);

    const handleSaveName = async (e: FormEvent) => {
        e.preventDefault();
        setSavingName(true);
        setNameMessage(null);
        setNameErrors({});

        try {
            const updated = await api.updateProfile(displayName);
            setProfile(updated);
            setDisplayName(updated.displayName ?? '');
            setNameMessage('Сохранено. Имя обновится в меню после перезагрузки страницы.');
            playSound('success');
        } catch (err) {
            playSound('error');
            if (err instanceof ApiError) {
                setNameErrors(err.errors ?? {});
                setNameMessage(err.errors ? null : err.message);
            } else {
                setNameMessage('Не удалось сохранить имя.');
            }
        }

        setSavingName(false);
    };

    const handleLocaleSwitch = async (locale: 'ru' | 'en') => {
        // Активный язык (сохранённый или текущий язык страницы) — повторный клик не нужен
        const active = profile?.locale ?? (document.documentElement.lang === 'en' ? 'en' : 'ru');

        if (localeBusy || locale === active) {
            return;
        }

        setLocaleBusy(true);
        setLocaleError(null);

        try {
            await api.updateLocale(locale);
            window.location.reload();
        } catch (err) {
            playSound('error');
            setLocaleError(err instanceof ApiError
                ? err.errors?.locale ?? err.message
                : t('frontend.profile.locale.error', 'Не удалось сменить язык.'));
            setLocaleBusy(false);
        }
    };

    const handleAvatarChange = async (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';

        if (!file) {
            return;
        }

        if (file.size > AVATAR_MAX_BYTES) {
            setAvatarError('Файл больше 2 МБ.');
            return;
        }

        setAvatarBusy(true);
        setAvatarError(null);

        try {
            setProfile(await api.uploadAvatar(file));
            playSound('success');
        } catch (err) {
            playSound('error');
            setAvatarError(err instanceof ApiError
                ? err.errors?.avatar ?? err.message
                : 'Не удалось загрузить аватар.');
        }

        setAvatarBusy(false);
    };

    const handleAvatarDelete = async () => {
        setAvatarBusy(true);
        setAvatarError(null);

        try {
            setProfile(await api.deleteAvatar());
        } catch (err) {
            setAvatarError(err instanceof ApiError ? err.message : 'Не удалось удалить аватар.');
        }

        setAvatarBusy(false);
    };

    if (loadError) {
        return <Alert kind="error">{loadError}</Alert>;
    }

    if (!profile) {
        return <p className="profile__loading">Загружаем профиль…</p>;
    }

    return (
        <div className="profile">
            <section className="card profile__section">
                <h2 className="profile__section-title">Профиль</h2>

                <div className="profile__avatar-row">
                    <span className="profile__avatar" aria-hidden="true">
                        {profile.avatarUrl
                            ? <img className="profile__avatar-img" src={profile.avatarUrl} alt="" />
                            : profile.initials}
                    </span>
                    <div className="profile__avatar-actions">
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="profile__avatar-input"
                            onChange={handleAvatarChange}
                            aria-label="Загрузить аватар"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            loading={avatarBusy}
                            onClick={() => fileInput.current?.click()}
                        >
                            {profile.avatarUrl ? 'Заменить фото' : 'Загрузить фото'}
                        </Button>
                        {profile.avatarUrl && (
                            <Button type="button" variant="ghost" size="sm" onClick={handleAvatarDelete}>
                                Убрать
                            </Button>
                        )}
                        <p className="profile__avatar-hint">JPEG, PNG или WebP до 2 МБ — обрежется в квадрат 256×256.</p>
                        {avatarError && <span className="field__error">{avatarError}</span>}
                    </div>
                </div>

                <form onSubmit={handleSaveName} noValidate>
                    {nameMessage && <Alert kind={nameErrors.displayName ? 'error' : 'success'}>{nameMessage}</Alert>}
                    <TextField
                        id="profile-name"
                        label="Отображаемое имя"
                        placeholder="Например, Анна Скрипичная"
                        value={displayName}
                        error={nameErrors.displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                        maxLength={80}
                    />
                    <p className="profile__field-hint">Показывается в меню вместо email. Пустое поле — вернуть email.</p>
                    <div className="profile__actions">
                        <Button type="submit" loading={savingName}>Сохранить</Button>
                    </div>
                </form>
            </section>

            <section className="card profile__section">
                <h2 className="profile__section-title">{t('frontend.profile.locale.title', 'Язык интерфейса')}</h2>
                <p className="profile__field-hint">
                    {t('frontend.profile.locale.hint', 'Сохраняется в профиле и действует на всех ваших устройствах.')}
                </p>
                <div className="profile__actions profile__locale-actions">
                    {(['ru', 'en'] as const).map((locale) => {
                        const active = (profile.locale ?? (document.documentElement.lang === 'en' ? 'en' : 'ru')) === locale;

                        return (
                            <Button
                                key={locale}
                                type="button"
                                variant={active ? 'primary' : 'ghost'}
                                size="sm"
                                loading={localeBusy && !active}
                                aria-pressed={active}
                                onClick={() => handleLocaleSwitch(locale)}
                            >
                                {locale === 'ru' ? 'Русский' : 'English'}
                            </Button>
                        );
                    })}
                </div>
                {localeError && <span className="field__error">{localeError}</span>}
            </section>

            <section className="card profile__section">
                <h2 className="profile__section-title">Безопасность</h2>
                <form onSubmit={security.handleSubmit} noValidate>
                    {passwordSaved && <Alert kind="success">Пароль изменён.</Alert>}
                    {security.errors.general && <Alert kind="error">{security.errors.general}</Alert>}
                    <TextField
                        id="profile-current-password"
                        label="Текущий пароль"
                        type="password"
                        autoComplete="current-password"
                        required
                        {...security.fieldProps('currentPassword')}
                    />
                    <TextField
                        id="profile-new-password"
                        label="Новый пароль"
                        type="password"
                        autoComplete="new-password"
                        required
                        {...security.fieldProps('newPassword')}
                    />
                    <PasswordStrength value={security.values.newPassword} />
                    <TextField
                        id="profile-new-password-confirm"
                        label="Новый пароль ещё раз"
                        type="password"
                        autoComplete="new-password"
                        required
                        {...security.fieldProps('newPasswordConfirm')}
                    />
                    <div className="profile__actions">
                        <Button type="submit" loading={security.submitting}>Изменить пароль</Button>
                    </div>
                </form>
            </section>

            <section className="card profile__section">
                <h2 className="profile__section-title">Двухфакторная аутентификация</h2>
                <TwoFactorPanel api={api} enabled={profile.totpEnabled} onChanged={loadProfile} />
            </section>

            <section className="card profile__section">
                <h2 className="profile__section-title">Активные сессии</h2>
                <p className="profile__field-hint profile__sessions-hint">
                    Где открыт ваш аккаунт. Завершённая сессия попросит пароль при следующем действии.
                    Смена пароля завершает все сессии, кроме текущей.
                </p>
                <SessionsPanel />
            </section>

            <section className="card profile__section">
                <h2 className="profile__section-title">Аккаунт</h2>
                <dl className="profile__meta">
                    <div className="profile__meta-row">
                        <dt>Email</dt>
                        <dd>{profile.email}</dd>
                    </div>
                    <div className="profile__meta-row">
                        <dt>Роли</dt>
                        <dd>{profile.roles.map((r) => r.replace('ROLE_', '')).join(', ')}</dd>
                    </div>
                    <div className="profile__meta-row">
                        <dt>В petSymphony с</dt>
                        <dd>
                            {new Date(profile.createdAt).toLocaleDateString(uiLocale(), {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </dd>
                    </div>
                </dl>
                <p className="profile__field-hint">
                    Смена email появится вместе с подтверждением адреса. Тема и звуки — переключатели в шапке.
                </p>
            </section>
        </div>
    );
}
