import { ChangeEvent, FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Profile, ProfileApiService } from '../services/ProfileApiService';
import { ApiError } from '../services/httpClient';
import { playSound } from '../utils/sound';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import TextField from '../components/ui/TextField';

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

    // Секция «Безопасность»
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [newPasswordConfirm, setNewPasswordConfirm] = useState('');
    const [savingPassword, setSavingPassword] = useState(false);
    const [passwordMessage, setPasswordMessage] = useState<string | null>(null);
    const [passwordErrors, setPasswordErrors] = useState<FieldErrors>({});

    useEffect(() => {
        api.getProfile()
            .then((data) => {
                setProfile(data);
                setDisplayName(data.displayName ?? '');
            })
            .catch((err) => {
                setLoadError(err instanceof ApiError ? err.message : 'Не удалось загрузить профиль.');
            });
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

    const handleChangePassword = async (e: FormEvent) => {
        e.preventDefault();
        setSavingPassword(true);
        setPasswordMessage(null);
        setPasswordErrors({});

        try {
            await api.changePassword(currentPassword, newPassword, newPasswordConfirm);
            setCurrentPassword('');
            setNewPassword('');
            setNewPasswordConfirm('');
            setPasswordMessage('Пароль изменён.');
            playSound('success');
        } catch (err) {
            playSound('error');
            if (err instanceof ApiError) {
                setPasswordErrors(err.errors ?? {});
                setPasswordMessage(err.errors ? null : err.message);
            } else {
                setPasswordMessage('Не удалось изменить пароль.');
            }
        }

        setSavingPassword(false);
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
                <h2 className="profile__section-title">Безопасность</h2>
                <form onSubmit={handleChangePassword} noValidate>
                    {passwordMessage && (
                        <Alert kind={passwordMessage === 'Пароль изменён.' ? 'success' : 'error'}>
                            {passwordMessage}
                        </Alert>
                    )}
                    <TextField
                        id="profile-current-password"
                        label="Текущий пароль"
                        type="password"
                        autoComplete="current-password"
                        value={currentPassword}
                        error={passwordErrors.currentPassword}
                        onChange={(e) => setCurrentPassword(e.target.value)}
                        required
                    />
                    <TextField
                        id="profile-new-password"
                        label="Новый пароль"
                        type="password"
                        autoComplete="new-password"
                        value={newPassword}
                        error={passwordErrors.newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        required
                    />
                    <TextField
                        id="profile-new-password-confirm"
                        label="Новый пароль ещё раз"
                        type="password"
                        autoComplete="new-password"
                        value={newPasswordConfirm}
                        error={passwordErrors.newPasswordConfirm}
                        onChange={(e) => setNewPasswordConfirm(e.target.value)}
                        required
                    />
                    <div className="profile__actions">
                        <Button type="submit" loading={savingPassword}>Изменить пароль</Button>
                    </div>
                </form>
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
                            {new Date(profile.createdAt).toLocaleDateString('ru-RU', {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </dd>
                    </div>
                </dl>
                <p className="profile__field-hint">
                    Смена email появится вместе с подтверждением адреса. Язык и звуки — переключатели в шапке.
                </p>
            </section>
        </div>
    );
}
