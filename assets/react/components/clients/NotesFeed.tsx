import { KeyboardEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { required } from '../../hooks/rules';
import { useForm } from '../../hooks/useForm';
import { Note, NoteApiService } from '../../services/NoteApiService';
import { ApiError } from '../../services/httpClient';
import { formatRelative } from '../../utils/relativeTime';
import Alert from '../ui/Alert';
import Button from '../ui/Button';

interface NotesFeedProps {
    clientId: number;
}

const authorLabel = (email: string): string => email.split('@')[0] ?? email;

export default function NotesFeed({ clientId }: NotesFeedProps) {
    const [notes, setNotes] = useState<Note[]>([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);


    const [editingId, setEditingId] = useState<number | null>(null);
    const [editContent, setEditContent] = useState('');
    const [savingEdit, setSavingEdit] = useState(false);
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const apiService = useMemo(() => new NoteApiService(), []);
    const hasMore = notes.length < total;

    const loadFirstPage = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.getNotes(clientId, 1);
            setNotes(data.data);
            setTotal(data.total);
            setPage(1);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить заметки.');
        }

        setLoading(false);
    }, [apiService, clientId]);

    useEffect(() => {
        void loadFirstPage();
    }, [loadFirstPage]);

    const loadMore = async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.getNotes(clientId, page + 1);
            setNotes((prev) => [...prev, ...data.data]);
            setTotal(data.total);
            setPage(data.page);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось загрузить заметки.');
        }

        setLoading(false);
    };

    // Композер — на общем каркасе форм
    const composer = useForm({
        initial: { content: '' },
        rules: { content: [required('Заметка не может быть пустой.')] },
        fallbackError: 'Не удалось сохранить заметку.',
        onSubmit: async (values) => {
            const note = await apiService.addNote(clientId, values.content.trim());
            setNotes((prev) => [note, ...prev]);
            setTotal((t) => t + 1);
            composer.setValue('content', '');
        },
    });

    const handleComposerKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            // Родная отправка формы — сработает onSubmit каркаса
            e.currentTarget.form?.requestSubmit();
        }
    };

    const startEdit = (note: Note) => {
        setEditingId(note.id);
        setEditContent(note.content);
        setConfirmDeleteId(null);
    };

    const handleSaveEdit = async (noteId: number) => {
        if (editContent.trim() === '') {
            return;
        }

        setSavingEdit(true);
        setError(null);

        try {
            const updated = await apiService.updateNote(noteId, editContent.trim());
            setNotes((prev) => prev.map((n) => (n.id === updated.id ? updated : n)));
            setEditingId(null);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось сохранить правку.');
        }

        setSavingEdit(false);
    };

    const handleDelete = async (noteId: number) => {
        if (confirmDeleteId !== noteId) {
            // Первый клик — только «взводит» подтверждение, без window.confirm
            setConfirmDeleteId(noteId);
            return;
        }

        setDeletingId(noteId);
        setError(null);

        try {
            await apiService.deleteNote(noteId);
            setNotes((prev) => prev.filter((n) => n.id !== noteId));
            setTotal((t) => Math.max(0, t - 1));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось удалить заметку.');
        }

        setConfirmDeleteId(null);
        setDeletingId(null);
    };

    return (
        <div className="notes" aria-busy={loading}>
            <form className="notes__composer" onSubmit={composer.handleSubmit} noValidate>
                <label className="field__label" htmlFor={`note-composer-${clientId}`}>
                    Новая заметка
                </label>
                <textarea
                    id={`note-composer-${clientId}`}
                    className="field__input notes__textarea"
                    rows={3}
                    placeholder="Что разобрали, что задали, о чём договорились… (Ctrl+Enter — сохранить)"
                    value={composer.values.content}
                    onChange={(e) => composer.setValue('content', e.currentTarget.value)}
                    onKeyDown={handleComposerKeyDown}
                />
                {(composer.errors.content ?? composer.errors.general) && (
                    <span className="field__error">{composer.errors.content ?? composer.errors.general}</span>
                )}
                <div className="notes__composer-actions">
                    <Button type="submit" variant="brass" size="sm" loading={composer.submitting}>
                        {composer.submitting ? 'Сохраняем…' : 'Записать'}
                    </Button>
                </div>
            </form>

            {error && <Alert kind="error">{error}</Alert>}

            {notes.length === 0 && !loading && (
                <p className="notes__empty">Первая запись в партитуре ученика — после первого занятия.</p>
            )}

            <ul className="notes__list">
                {notes.map((note) => (
                    <li key={note.id} className="notes__item">
                        <div className="notes__meta">
                            <span className="notes__author">{authorLabel(note.authorEmail)}</span>
                            <time className="notes__time" dateTime={note.createdAt} title={note.createdAt}>
                                {formatRelative(note.createdAt)}
                            </time>
                            {note.updatedAt && <span className="notes__edited">· изменено</span>}
                            {note.manageable && editingId !== note.id && (
                                <span className="notes__item-actions">
                                    <button type="button" className="notes__link-btn" onClick={() => startEdit(note)}>
                                        Изменить
                                    </button>
                                    <button
                                        type="button"
                                        className={`notes__link-btn${confirmDeleteId === note.id ? ' notes__link-btn--danger' : ''}`}
                                        disabled={deletingId === note.id}
                                        onClick={() => handleDelete(note.id)}
                                    >
                                        {confirmDeleteId === note.id ? 'Точно удалить?' : 'Удалить'}
                                    </button>
                                </span>
                            )}
                        </div>

                        {editingId === note.id ? (
                            <div className="notes__edit">
                                <textarea
                                    className="field__input notes__textarea"
                                    rows={3}
                                    value={editContent}
                                    onChange={(e) => setEditContent(e.target.value)}
                                    aria-label="Редактирование заметки"
                                />
                                <div className="notes__composer-actions">
                                    <Button
                                        type="button"
                                        variant="brass"
                                        size="sm"
                                        loading={savingEdit}
                                        onClick={() => handleSaveEdit(note.id)}
                                    >
                                        Сохранить
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setEditingId(null)}>
                                        Отмена
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <p className="notes__content">{note.content}</p>
                        )}
                    </li>
                ))}
            </ul>

            {hasMore && (
                <div className="notes__more">
                    <Button type="button" variant="ghost" size="sm" loading={loading} onClick={loadMore}>
                        Показать ещё ({total - notes.length})
                    </Button>
                </div>
            )}
        </div>
    );
}
