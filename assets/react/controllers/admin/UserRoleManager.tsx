import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminUser, RbacApiService, UserRole } from '../../services/RbacApiService';
import { ApiError } from '../../services/httpClient';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';

interface UserRoleManagerProps {
    currentUserId: number;
}

const TOGGLABLE_ROLES: { role: UserRole; label: string; modifier: string }[] = [
    { role: 'ROLE_USER', label: 'USER', modifier: 'user' },
    { role: 'ROLE_MODERATOR', label: 'MODERATOR', modifier: 'moderator' },
    { role: 'ROLE_ADMIN', label: 'ADMIN', modifier: 'admin' },
];

const sortRoles = (roles: UserRole[]): UserRole[] =>
    TOGGLABLE_ROLES.map(({ role }) => role).filter((role) => roles.includes(role));

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });

export default function UserRoleManager({ currentUserId }: UserRoleManagerProps) {
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(20);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [savingId, setSavingId] = useState<number | null>(null);
    const [drafts, setDrafts] = useState<Record<number, UserRole[]>>({});
    const [error, setError] = useState<string | null>(null);

    const apiService = useMemo(() => new RbacApiService(), []);
    const totalPages = Math.max(1, Math.ceil(total / perPage));

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    const loadUsers = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.getUsers(page, debouncedSearch, perPage);
            setUsers(data.users);
            setTotal(data.total);
            setPerPage(data.perPage);
            setDrafts({});
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить пользователей. Обновите страницу.');
        }

        setLoading(false);
    }, [apiService, page, debouncedSearch, perPage]);

    useEffect(() => {
        void loadUsers();
    }, [loadUsers]);

    const rolesOf = (user: AdminUser): UserRole[] => drafts[user.id] ?? sortRoles(user.roles);

    const isDirty = (user: AdminUser): boolean =>
        drafts[user.id] !== undefined
        && JSON.stringify(drafts[user.id]) !== JSON.stringify(sortRoles(user.roles));

    const toggleRole = (user: AdminUser, role: UserRole) => {
        const current = rolesOf(user);
        const next = current.includes(role)
            ? current.filter((r) => r !== role)
            : sortRoles([...current, role]);

        setDrafts((prev) => ({ ...prev, [user.id]: next }));
    };

    const handleSave = async (user: AdminUser) => {
        setSavingId(user.id);
        setError(null);

        try {
            const updated = await apiService.updateRoles(user.id, rolesOf(user));
            setUsers((prev) => prev.map((u) => (u.id === updated.id ? updated : u)));
            setDrafts((prev) => {
                const { [user.id]: _, ...rest } = prev;
                return rest;
            });
        } catch (err) {
            if (err instanceof ApiError) {
                setError(err.errors?.roles ?? err.errors?.general ?? err.message);
            } else {
                setError('Не удалось сохранить роли.');
            }
        }

        setSavingId(null);
    };

    return (
        <div className="users">
            <div className="users__toolbar">
                <div className="users__search">
                    <span className="users__search-icon" aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        className="field__input"
                        placeholder="Поиск по email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        aria-label="Поиск по email"
                    />
                </div>
                <div className="users__count">
                    Всего: <strong>{total}</strong>
                </div>
            </div>

            {error && <Alert kind="error">{error}</Alert>}

            <div className="card">
                <table className="users-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Роли</th>
                            <th>Создан</th>
                            <th aria-label="Действия" />
                        </tr>
                    </thead>
                    <tbody>
                        {users.length === 0 && !loading && (
                            <tr>
                                <td colSpan={4}>
                                    <div className="users__empty">
                                        <span className="users__empty-glyph" aria-hidden="true">♪</span>
                                        {debouncedSearch
                                            ? 'Никого не нашли. Попробуйте другой запрос.'
                                            : 'Пока ни одного пользователя.'}
                                    </div>
                                </td>
                            </tr>
                        )}
                        {users.map((user) => {
                            const isSelf = user.id === currentUserId;
                            const roles = rolesOf(user);

                            return (
                                <tr key={user.id}>
                                    <td className="users-table__email">
                                        {user.email}
                                        {isSelf && <span className="badge users-table__you">это вы</span>}
                                    </td>
                                    <td>
                                        <div className="users-table__roles">
                                            {TOGGLABLE_ROLES.map(({ role, label, modifier }) => {
                                                const active = roles.includes(role);
                                                const locked = role === 'ROLE_USER' || (isSelf && role === 'ROLE_ADMIN');

                                                return (
                                                    <button
                                                        key={role}
                                                        type="button"
                                                        className={`chip chip--${modifier}${active ? ' chip--on' : ''}`}
                                                        disabled={locked}
                                                        title={locked
                                                            ? (role === 'ROLE_USER'
                                                                ? 'Базовая роль — есть у всех'
                                                                : 'Нельзя снять роль администратора с самого себя')
                                                            : undefined}
                                                        aria-pressed={active}
                                                        onClick={() => toggleRole(user, role)}
                                                    >
                                                        {active ? '✓ ' : ''}{label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td className="users-table__date">{formatDate(user.createdAt)}</td>
                                    <td className="users-table__action">
                                        {isDirty(user) && (
                                            <Button
                                                size="sm"
                                                variant="brass"
                                                loading={savingId === user.id}
                                                onClick={() => handleSave(user)}
                                            >
                                                Сохранить
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {totalPages > 1 && (
                <div className="users__pagination">
                    <button
                        type="button"
                        className="users__page-btn"
                        disabled={page <= 1 || loading}
                        onClick={() => setPage((p) => p - 1)}
                        aria-label="Предыдущая страница"
                    >
                        ‹
                    </button>
                    <span>{page} / {totalPages}</span>
                    <button
                        type="button"
                        className="users__page-btn"
                        disabled={page >= totalPages || loading}
                        onClick={() => setPage((p) => p + 1)}
                        aria-label="Следующая страница"
                    >
                        ›
                    </button>
                </div>
            )}

            <div className="users__roles-legend">
                <span className="users__legend-admin"><i />ADMIN — «дирижёр»: полный доступ и управление ролями</span>
                <span className="users__legend-mod"><i />MODERATOR — «первая скрипка»: модерация контента</span>
                <span className="users__legend-user"><i />USER — «музыкант»: базовый доступ</span>
            </div>
        </div>
    );
}
