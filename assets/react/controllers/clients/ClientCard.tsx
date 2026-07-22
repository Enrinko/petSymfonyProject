import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Client, ClientApiService } from '../../services/ClientApiService';
import { TagApiService, TagInfo } from '../../services/TagApiService';
import { Instrument, InstrumentApiService } from '../../services/InstrumentApiService';
import { ApiError } from '../../services/httpClient';
import { formatPhoneInput } from '../../utils/phoneMask';
import { tagColorIndex } from '../../utils/tagColor';
import { instrumentIcon } from '../../utils/instrumentIcon';
import { validateClientInput } from '../../utils/validateClientInput';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';
import TextField from '../../components/ui/TextField';
import NotesFeed from '../../components/clients/NotesFeed';
import TagInput from '../../components/clients/TagInput';
import InstrumentPicker from '../../components/clients/InstrumentPicker';

interface ClientCardProps {
    clientId: number;
    listUrl: string;
}

const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

export default function ClientCard({ clientId, listUrl }: ClientCardProps) {
    const [client, setClient] = useState<Client | null>(null);
    const [notFound, setNotFound] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState({ name: '', email: '', phone: '', comment: '' });
    const [formTags, setFormTags] = useState<string[]>([]);
    const [formInstruments, setFormInstruments] = useState<number[]>([]);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [busy, setBusy] = useState(false);
    const [allTags, setAllTags] = useState<TagInfo[]>([]);
    const [catalog, setCatalog] = useState<Instrument[]>([]);

    const apiService = useMemo(() => new ClientApiService(), []);
    const tagApiService = useMemo(() => new TagApiService(), []);
    const instrumentApiService = useMemo(() => new InstrumentApiService(), []);

    useEffect(() => {
        tagApiService.getTags()
            .then((res) => setAllTags(res.data))
            .catch(() => { /* подсказки не критичны */ });
        instrumentApiService.getCatalog()
            .then((c) => setCatalog(c.data))
            .catch(() => { /* справочник не критичен */ });
    }, [tagApiService, instrumentApiService]);

    const loadClient = useCallback(async () => {
        setError(null);

        try {
            setClient(await apiService.getClient(clientId));
        } catch (err) {
            if (err instanceof ApiError && err.status === 404) {
                setNotFound(true);
            } else {
                setError(err instanceof ApiError ? err.message : 'Не удалось загрузить карточку.');
            }
        }
    }, [apiService, clientId]);

    useEffect(() => {
        void loadClient();
    }, [loadClient]);

    const startEditing = () => {
        if (!client) {
            return;
        }

        setForm({
            name: client.name,
            email: client.email ?? '',
            // Маска нормализует и записи, созданные до её появления
            phone: client.phone ? formatPhoneInput(client.phone) : '',
            comment: client.comment ?? '',
        });
        setFormTags(client.tags);
        setFormInstruments(client.instruments.map((i) => i.id));
        setFormErrors({});
        setEditing(true);
    };

    const handleSave = async (e: FormEvent) => {
        e.preventDefault();

        const clientErrors = validateClientInput(form);

        if (Object.keys(clientErrors).length > 0) {
            setFormErrors(clientErrors);
            return;
        }

        setSaving(true);
        setFormErrors({});

        try {
            const updated = await apiService.updateClient(clientId, {
                name: form.name.trim(),
                email: form.email.trim() || null,
                phone: form.phone.trim() || null,
                comment: form.comment.trim() || null,
                tags: formTags,
                instrumentIds: formInstruments,
            });
            setClient(updated);
            setEditing(false);
        } catch (err) {
            if (err instanceof ApiError) {
                setFormErrors(err.errors ?? { general: err.message });
            } else {
                setFormErrors({ general: 'Не удалось сохранить. Попробуйте ещё раз.' });
            }
        }

        setSaving(false);
    };

    const handleArchiveToggle = async () => {
        if (!client) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            if (client.archivedAt) {
                setClient(await apiService.restoreClient(client.id));
            } else {
                await apiService.archiveClient(client.id);
                await loadClient();
            }
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось выполнить действие.');
        }

        setBusy(false);
    };

    if (notFound) {
        return (
            <div className="client-card__missing">
                <Alert kind="error">Ученик не найден или недоступен.</Alert>
                <Button type="button" variant="ghost" onClick={() => window.location.assign(listUrl)}>
                    ← Ко всем ученикам
                </Button>
            </div>
        );
    }

    if (!client) {
        return (
            <div className="client-card__loading" aria-busy="true">
                {error ? <Alert kind="error">{error}</Alert> : 'Загружаем карточку…'}
            </div>
        );
    }

    return (
        <div className="client-card">
            {client.archivedAt && (
                <Alert kind="error" className="client-card__archived-note">
                    Ученик в архиве с {formatDateTime(client.archivedAt)}. Записи сохранены, но в общем списке он скрыт.
                </Alert>
            )}

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card client-card__head">
                {!editing && (
                    <>
                        <div className="client-card__identity">
                            <h2 className="client-card__name">{client.name}</h2>
                            <div className="client-card__contacts">
                                {client.email && <a href={`mailto:${client.email}`}>{client.email}</a>}
                                {client.phone && <span>{client.phone}</span>}
                                {!client.email && !client.phone && (
                                    <span className="client-card__muted">контакты не указаны</span>
                                )}
                            </div>
                            {client.tags.length > 0 && (
                                <div className="client-card__tags">
                                    {client.tags.map((tag) => (
                                        <span key={tag} className={`tag-chip tag-chip--c${tagColorIndex(tag)}`}>
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            )}
                            {client.instruments.length > 0 && (
                                <div className="client-card__instruments">
                                    {client.instruments.map((i) => (
                                        <span key={i.id} className="instrument-chip">
                                            <span aria-hidden="true">{instrumentIcon(i.category)}</span> {i.name}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="client-card__actions">
                            <Button type="button" variant="brass" onClick={startEditing}>
                                Редактировать
                            </Button>
                            <Button type="button" variant="ghost" loading={busy} onClick={handleArchiveToggle}>
                                {client.archivedAt ? 'Вернуть из архива' : 'В архив'}
                            </Button>
                        </div>
                    </>
                )}

                {editing && (
                    <form className="client-card__form" onSubmit={handleSave} noValidate>
                        <div className="client-card__form-grid">
                            <TextField
                                id="edit-name"
                                label="Имя"
                                value={form.name}
                                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                                error={formErrors.name}
                                required
                                autoFocus
                            />
                            <TextField
                                id="edit-email"
                                label="Email"
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                                error={formErrors.email}
                            />
                            <TextField
                                id="edit-phone"
                                label="Телефон"
                                type="tel"
                                placeholder="+7 (900) 123-45-67"
                                value={form.phone}
                                onChange={(e) => setForm((p) => ({ ...p, phone: formatPhoneInput(e.target.value) }))}
                                error={formErrors.phone}
                            />
                        </div>
                        <TagInput
                            id="edit-tags"
                            label="Теги"
                            value={formTags}
                            onChange={setFormTags}
                            suggestions={allTags.map((t) => t.name)}
                            error={formErrors.tags}
                        />
                        <InstrumentPicker
                            label="Инструменты"
                            catalog={catalog}
                            selected={formInstruments}
                            onChange={setFormInstruments}
                        />
                        <div className="field">
                            <label className="field__label" htmlFor="edit-comment">Комментарий</label>
                            <textarea
                                id="edit-comment"
                                className="field__input client-card__textarea"
                                rows={3}
                                value={form.comment}
                                onChange={(e) => setForm((p) => ({ ...p, comment: e.target.value }))}
                            />
                            {formErrors.comment && <span className="field__error">{formErrors.comment}</span>}
                        </div>
                        {formErrors.general && <Alert kind="error">{formErrors.general}</Alert>}
                        <div className="client-card__actions">
                            <Button type="submit" variant="brass" loading={saving}>
                                {saving ? 'Сохраняем…' : 'Сохранить'}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setEditing(false)}>
                                Отмена
                            </Button>
                        </div>
                    </form>
                )}
            </div>

            <div className="card client-card__overview">
                <div className="card__header">
                    <h3 className="card__title">Обзор</h3>
                </div>
                <div className="card__body">
                    <dl className="client-card__facts">
                        <div>
                            <dt>Комментарий</dt>
                            <dd className="client-card__comment">
                                {client.comment ?? <span className="client-card__muted">пока пусто</span>}
                            </dd>
                        </div>
                        <div>
                            <dt>Добавлен</dt>
                            <dd>{formatDateTime(client.createdAt)}</dd>
                        </div>
                        {client.updatedAt && (
                            <div>
                                <dt>Изменён</dt>
                                <dd>{formatDateTime(client.updatedAt)}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>

            <div className="card client-card__notes">
                <div className="card__header">
                    <h3 className="card__title">Заметки</h3>
                </div>
                <div className="card__body">
                    <NotesFeed clientId={clientId} />
                </div>
            </div>

            <div className="client-card__coming">
                <span className="badge">Репертуар · скоро</span>
                <span className="badge">Посещаемость · скоро</span>
            </div>
        </div>
    );
}
