import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Client, ClientApiService } from '../../services/ClientApiService';
import { TagApiService, TagInfo } from '../../services/TagApiService';
import { ApiError } from '../../services/httpClient';
import { formatPhoneInput } from '../../utils/phoneMask';
import { tagColorIndex } from '../../utils/tagColor';
import { validateClientInput } from '../../utils/validateClientInput';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';
import TextField from '../../components/ui/TextField';
import TagInput from '../../components/clients/TagInput';
import ImportPanel from '../../components/clients/ImportPanel';

interface ClientListProps {
    clientsBasePath: string;
}

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });

const EMPTY_FORM = { name: '', email: '', phone: '', comment: '' };

export default function ClientList({ clientsBasePath }: ClientListProps) {
    const [clients, setClients] = useState<Client[]>([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [limit, setLimit] = useState(20);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [showArchived, setShowArchived] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [panelOpen, setPanelOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [formTags, setFormTags] = useState<string[]>([]);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);

    const [allTags, setAllTags] = useState<TagInfo[]>([]);
    // Начальный фильтр тегов из URL — переход из палитры поиска по тегу (?tags=…)
    const [filterTags, setFilterTags] = useState<string[]>(() => {
        const raw = new URLSearchParams(window.location.search).get('tags');
        return raw ? raw.split(',').map((t) => t.trim().toLowerCase()).filter(Boolean) : [];
    });
    const [importOpen, setImportOpen] = useState(false);

    const apiService = useMemo(() => new ClientApiService(), []);
    const tagApiService = useMemo(() => new TagApiService(), []);
    const totalPages = Math.max(1, Math.ceil(total / limit));

    const loadTags = useCallback(async () => {
        try {
            setAllTags((await tagApiService.getTags()).data);
        } catch {
            // Автодополнение и фильтр без подсказок — не критично для работы списка.
        }
    }, [tagApiService]);

    useEffect(() => {
        void loadTags();
    }, [loadTags]);

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    const loadClients = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.getClients(page, debouncedSearch, showArchived, limit, filterTags);
            setClients(data.data);
            setTotal(data.total);
            setLimit(data.limit);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить учеников. Обновите страницу.');
        }

        setLoading(false);
    }, [apiService, page, debouncedSearch, showArchived, limit, filterTags]);

    useEffect(() => {
        void loadClients();
    }, [loadClients]);

    const toggleFilterTag = (name: string) => {
        setFilterTags((prev) => (prev.includes(name) ? prev.filter((t) => t !== name) : [...prev, name]));
        setPage(1);
    };

    const openCreate = () => {
        setForm(EMPTY_FORM);
        setFormTags([]);
        setFormErrors({});
        setEditingId(null);
        setPanelOpen(true);
    };

    const openEdit = (client: Client) => {
        setForm({
            name: client.name,
            email: client.email ?? '',
            // Прогон через маску нормализует и старые записи вида «89001234567»
            phone: client.phone ? formatPhoneInput(client.phone) : '',
            comment: client.comment ?? '',
        });
        setFormTags(client.tags);
        setFormErrors({});
        setEditingId(client.id);
        setPanelOpen(true);
    };

    const closePanel = () => {
        setPanelOpen(false);
        setEditingId(null);
        setFormErrors({});
    };

    const setField = (name: keyof typeof EMPTY_FORM) =>
        (e: FormEvent<HTMLInputElement>) => {
            const { value } = e.currentTarget;
            setForm((prev) => ({
                ...prev,
                [name]: name === 'phone' ? formatPhoneInput(value) : value,
            }));
        };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();

        const clientErrors = validateClientInput(form);

        if (Object.keys(clientErrors).length > 0) {
            setFormErrors(clientErrors);
            return;
        }

        setSaving(true);
        setFormErrors({});

        const input = {
            name: form.name.trim(),
            email: form.email.trim() || null,
            phone: form.phone.trim() || null,
            comment: form.comment.trim() || null,
            tags: formTags,
        };

        try {
            if (editingId === null) {
                await apiService.createClient(input);
            } else {
                await apiService.updateClient(editingId, input);
            }

            closePanel();
            setForm(EMPTY_FORM);
            await Promise.all([loadClients(), loadTags()]);
        } catch (err) {
            if (err instanceof ApiError) {
                setFormErrors(err.errors ?? { general: err.message });
            } else {
                setFormErrors({ general: 'Не удалось сохранить. Попробуйте ещё раз.' });
            }
        }

        setSaving(false);
    };

    const handleArchiveToggle = async (client: Client) => {
        setBusyId(client.id);
        setError(null);

        try {
            if (client.archivedAt) {
                await apiService.restoreClient(client.id);
            } else {
                await apiService.archiveClient(client.id);
            }
            await loadClients();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось выполнить действие.');
        }

        setBusyId(null);
    };

    return (
        <div className="clients">
            <div className="clients__toolbar">
                <div className="clients__search">
                    <span className="clients__search-icon" aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        className="field__input"
                        placeholder="Поиск по имени, email, телефону…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        aria-label="Поиск учеников"
                    />
                </div>
                <label className="clients__archived-toggle">
                    <input
                        type="checkbox"
                        checked={showArchived}
                        onChange={(e) => { setShowArchived(e.target.checked); setPage(1); }}
                    />
                    <span>показать архив</span>
                </label>
                <div className="clients__count" aria-live="polite">
                    Всего: <strong>{total}</strong>
                </div>
                <a
                    className="btn btn--ghost btn--sm"
                    href={`/api/clients/export?${new URLSearchParams({
                        search: debouncedSearch,
                        ...(showArchived ? { archived: '1' } : {}),
                        ...(filterTags.length > 0 ? { tags: filterTags.join(',') } : {}),
                    })}`}
                >
                    Экспорт CSV
                </a>
                <Button variant="ghost" size="sm" onClick={() => setImportOpen((v) => !v)}>
                    Импорт
                </Button>
                <Button variant="brass" onClick={panelOpen && editingId === null ? closePanel : openCreate}>
                    {panelOpen && editingId === null ? 'Скрыть форму' : '+ Новый ученик'}
                </Button>
            </div>

            {importOpen && (
                <ImportPanel
                    onImported={() => { void Promise.all([loadClients(), loadTags()]); }}
                    onClose={() => setImportOpen(false)}
                />
            )}

            {allTags.length > 0 && (
                <div className="clients__tagbar" aria-label="Фильтр по тегам">
                    {allTags
                        .filter((tag) => tag.usageCount > 0 || filterTags.includes(tag.name))
                        .slice(0, 15)
                        .map((tag) => (
                            <button
                                key={tag.id}
                                type="button"
                                className={`tag-chip tag-chip--c${tagColorIndex(tag.name)}${filterTags.includes(tag.name) ? ' tag-chip--on' : ''}`}
                                aria-pressed={filterTags.includes(tag.name)}
                                onClick={() => toggleFilterTag(tag.name)}
                            >
                                {tag.name}
                                <span className="tag-chip__count">{tag.usageCount}</span>
                            </button>
                        ))}
                    {filterTags.length > 0 && (
                        <button type="button" className="notes__link-btn" onClick={() => { setFilterTags([]); setPage(1); }}>
                            сбросить
                        </button>
                    )}
                </div>
            )}

            {panelOpen && (
                <form className="card clients__create" onSubmit={handleSubmit} noValidate>
                    <h3 className="clients__create-title">
                        {editingId === null ? 'Новый ученик' : `Редактирование: ${form.name || '…'}`}
                    </h3>
                    <div className="clients__create-grid">
                        <TextField
                            id="client-name"
                            label="Имя"
                            placeholder="Анна Скрипкина"
                            value={form.name}
                            onChange={setField('name')}
                            error={formErrors.name}
                            required
                            autoFocus
                        />
                        <TextField
                            id="client-email"
                            label="Email"
                            type="email"
                            placeholder="anna@example.com"
                            value={form.email}
                            onChange={setField('email')}
                            error={formErrors.email}
                        />
                        <TextField
                            id="client-phone"
                            label="Телефон"
                            type="tel"
                            placeholder="+7 (900) 123-45-67"
                            value={form.phone}
                            onChange={setField('phone')}
                            error={formErrors.phone}
                        />
                        <TextField
                            id="client-comment"
                            label="Комментарий"
                            placeholder="вторник и четверг, разучиваем Черни"
                            value={form.comment}
                            onChange={setField('comment')}
                            error={formErrors.comment}
                        />
                        <TagInput
                            id="client-tags"
                            label="Теги"
                            value={formTags}
                            onChange={setFormTags}
                            suggestions={allTags.map((t) => t.name)}
                            error={formErrors.tags}
                        />
                    </div>
                    {formErrors.general && <Alert kind="error">{formErrors.general}</Alert>}
                    <div className="clients__create-actions">
                        <Button type="submit" variant="brass" loading={saving}>
                            {saving ? 'Сохраняем…' : editingId === null ? 'Добавить' : 'Сохранить'}
                        </Button>
                        <Button type="button" variant="ghost" onClick={closePanel}>
                            Отмена
                        </Button>
                    </div>
                </form>
            )}

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card" aria-busy={loading}>
                <table className="clients-table">
                    <thead>
                        <tr>
                            <th>Ученик</th>
                            <th>Контакты</th>
                            <th>Комментарий</th>
                            <th>Добавлен</th>
                            <th aria-label="Действия" />
                        </tr>
                    </thead>
                    <tbody>
                        {clients.length === 0 && !loading && (
                            <tr>
                                <td colSpan={5}>
                                    <div className="clients__empty">
                                        <span className="clients__empty-glyph" aria-hidden="true">♪</span>
                                        {debouncedSearch
                                            ? 'Никого не нашли. Попробуйте другой запрос.'
                                            : showArchived
                                                ? 'В архиве пусто.'
                                                : 'В ансамбле пока никого. Добавьте первого ученика.'}
                                    </div>
                                </td>
                            </tr>
                        )}
                        {clients.map((client) => (
                            <tr key={client.id} className={client.archivedAt ? 'clients-table__row--archived' : undefined}>
                                <td>
                                    <a className="clients-table__name" href={`${clientsBasePath}/${client.id}`}>
                                        {client.name}
                                    </a>
                                    {client.archivedAt && <span className="badge clients-table__badge">архив</span>}
                                    {client.tags.length > 0 && (
                                        <span className="clients-table__tags">
                                            {client.tags.map((tag) => (
                                                <span key={tag} className={`tag-chip tag-chip--sm tag-chip--c${tagColorIndex(tag)}`}>
                                                    {tag}
                                                </span>
                                            ))}
                                        </span>
                                    )}
                                </td>
                                <td className="clients-table__contacts">
                                    {client.email && <span>{client.email}</span>}
                                    {client.phone && <span>{client.phone}</span>}
                                    {!client.email && !client.phone && <span className="clients-table__muted">—</span>}
                                </td>
                                <td className="clients-table__comment" title={client.comment ?? undefined}>
                                    {client.comment ?? <span className="clients-table__muted">—</span>}
                                </td>
                                <td className="clients-table__date">{formatDate(client.createdAt)}</td>
                                <td className="clients-table__action">
                                    <Button size="sm" variant="ghost" onClick={() => openEdit(client)}>
                                        Изменить
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        loading={busyId === client.id}
                                        onClick={() => handleArchiveToggle(client)}
                                    >
                                        {client.archivedAt ? 'Вернуть' : 'В архив'}
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {totalPages > 1 && (
                <div className="clients__pagination">
                    <button
                        type="button"
                        className="clients__page-btn"
                        disabled={page <= 1 || loading}
                        onClick={() => setPage((p) => p - 1)}
                        aria-label="Предыдущая страница"
                    >
                        ‹
                    </button>
                    <span>{page} / {totalPages}</span>
                    <button
                        type="button"
                        className="clients__page-btn"
                        disabled={page >= totalPages || loading}
                        onClick={() => setPage((p) => p + 1)}
                        aria-label="Следующая страница"
                    >
                        ›
                    </button>
                </div>
            )}
        </div>
    );
}
