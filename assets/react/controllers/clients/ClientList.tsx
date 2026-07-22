import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Client, ClientApiService } from '../../services/ClientApiService';
import { ApiError } from '../../services/httpClient';
import { formatPhoneInput } from '../../utils/phoneMask';
import { validateClientInput } from '../../utils/validateClientInput';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';
import TextField from '../../components/ui/TextField';

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
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);

    const apiService = useMemo(() => new ClientApiService(), []);
    const totalPages = Math.max(1, Math.ceil(total / limit));

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
            const data = await apiService.getClients(page, debouncedSearch, showArchived, limit);
            setClients(data.data);
            setTotal(data.total);
            setLimit(data.limit);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить учеников. Обновите страницу.');
        }

        setLoading(false);
    }, [apiService, page, debouncedSearch, showArchived, limit]);

    useEffect(() => {
        void loadClients();
    }, [loadClients]);

    const openCreate = () => {
        setForm(EMPTY_FORM);
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
        };

        try {
            if (editingId === null) {
                await apiService.createClient(input);
            } else {
                await apiService.updateClient(editingId, input);
            }

            closePanel();
            setForm(EMPTY_FORM);
            await loadClients();
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
                <Button variant="brass" onClick={panelOpen && editingId === null ? closePanel : openCreate}>
                    {panelOpen && editingId === null ? 'Скрыть форму' : '+ Новый ученик'}
                </Button>
            </div>

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
                            placeholder="вокал, вторник и четверг"
                            value={form.comment}
                            onChange={setField('comment')}
                            error={formErrors.comment}
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
