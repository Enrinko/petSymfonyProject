import { useCallback, useEffect, useMemo, useState } from 'react';
import { required } from '../../hooks/rules';
import { useForm } from '../../hooks/useForm';
import { RepertoireApiService, RepertoirePiece } from '../../services/RepertoireApiService';
import { ApiError } from '../../services/httpClient';
import { t } from '../../i18n';
import Alert from '../ui/Alert';
import Button from '../ui/Button';

interface RepertoirePanelProps {
    clientId: number;
}

const STATUS_CLASS: Record<string, string> = {
    learning: 'piece-badge--learning',
    memorizing: 'piece-badge--memorizing',
    ready: 'piece-badge--ready',
    in_repertoire: 'piece-badge--done',
};

export default function RepertoirePanel({ clientId }: RepertoirePanelProps) {
    const [pieces, setPieces] = useState<RepertoirePiece[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);


    const [busyId, setBusyId] = useState<number | null>(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
    const [noteEditId, setNoteEditId] = useState<number | null>(null);
    const [noteDraft, setNoteDraft] = useState('');

    const apiService = useMemo(() => new RepertoireApiService(), []);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setPieces((await apiService.getPieces(clientId)).data);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.clients.repertoire.load_error', 'Не удалось загрузить репертуар.'));
        }

        setLoading(false);
    }, [apiService, clientId]);

    useEffect(() => {
        void load();
    }, [load]);

    // Добавление произведения — на общем каркасе форм
    const add = useForm({
        initial: { title: '', composer: '' },
        rules: { title: [required(t('frontend.clients.repertoire.title_required', 'Укажите название произведения.'))] },
        fallbackError: t('frontend.clients.repertoire.add_error', 'Не удалось добавить произведение.'),
        onSubmit: async (values) => {
            const piece = await apiService.addPiece(clientId, values.title.trim(), values.composer.trim() || null);
            setPieces((prev) => [piece, ...prev]);
            add.reset();
        },
    });

    const applyUpdate = (updated: RepertoirePiece) => {
        setPieces((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
    };

    const move = async (piece: RepertoirePiece, forward: boolean) => {
        setBusyId(piece.id);
        setError(null);

        try {
            applyUpdate(forward ? await apiService.advance(piece.id) : await apiService.stepBack(piece.id));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.clients.repertoire.status_error', 'Не удалось изменить статус.'));
        }

        setBusyId(null);
    };

    const saveNote = async (piece: RepertoirePiece) => {
        setBusyId(piece.id);

        try {
            applyUpdate(await apiService.updateNote(piece.id, noteDraft.trim()));
            setNoteEditId(null);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.clients.repertoire.note_save_error', 'Не удалось сохранить заметку.'));
        }

        setBusyId(null);
    };

    const remove = async (piece: RepertoirePiece) => {
        if (confirmDeleteId !== piece.id) {
            setConfirmDeleteId(piece.id);
            return;
        }

        setBusyId(piece.id);

        try {
            await apiService.remove(piece.id);
            setPieces((prev) => prev.filter((p) => p.id !== piece.id));
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.clients.repertoire.delete_error', 'Не удалось удалить произведение.'));
        }

        setConfirmDeleteId(null);
        setBusyId(null);
    };

    return (
        <div className="card rep" aria-busy={loading}>
            <div className="card__header">
                <h3 className="card__title">{t('frontend.clients.repertoire.title', 'Репертуар')}</h3>
            </div>
            <div className="card__body">
                <form className="rep__add" onSubmit={add.handleSubmit} noValidate>
                    <input
                        className="field__input rep__add-title"
                        placeholder={t('frontend.clients.repertoire.piece_title_label', 'Название произведения')}
                        value={add.values.title}
                        onChange={(e) => add.setValue('title', e.currentTarget.value)}
                        aria-label={t('frontend.clients.repertoire.piece_title_label', 'Название произведения')}
                    />
                    <input
                        className="field__input rep__add-composer"
                        placeholder={t('frontend.clients.repertoire.composer_label', 'Композитор')}
                        value={add.values.composer}
                        onChange={(e) => add.setValue('composer', e.currentTarget.value)}
                        aria-label={t('frontend.clients.repertoire.composer_label', 'Композитор')}
                    />
                    <Button type="submit" size="sm" variant="brass" loading={add.submitting}>{t('frontend.clients.repertoire.add_button', 'Добавить')}</Button>
                </form>
                {(add.errors.title ?? add.errors.composer ?? add.errors.general) && (
                    <span className="field__error">{add.errors.title ?? add.errors.composer ?? add.errors.general}</span>
                )}

                {error && <Alert kind="error">{error}</Alert>}

                {pieces.length === 0 && !loading && (
                    <p className="rep__empty">{t('frontend.clients.repertoire.empty', 'Пока пусто. Добавьте первое произведение — путь начнётся с «Разбираем».')}</p>
                )}

                <ul className="rep__list">
                    {pieces.map((piece) => (
                        <li key={piece.id} className="rep__item">
                            <div className="rep__item-main">
                                <span className="rep__item-title">{piece.title}</span>
                                {piece.composer && <span className="rep__item-composer">{piece.composer}</span>}
                                <span className={`piece-badge ${STATUS_CLASS[piece.status] ?? ''}`}>{piece.statusLabel}</span>
                            </div>

                            <div className="rep__item-actions">
                                <button
                                    type="button"
                                    className="rep__step"
                                    disabled={!piece.canStepBack || busyId === piece.id}
                                    title={t('frontend.clients.repertoire.step_back_title', 'Вернуть на шаг назад')}
                                    aria-label={t('frontend.clients.repertoire.step_back_aria', 'Вернуть «%title%» на шаг назад', { title: piece.title })}
                                    onClick={() => move(piece, false)}
                                >
                                    ←
                                </button>
                                <button
                                    type="button"
                                    className="rep__step rep__step--fw"
                                    disabled={!piece.canAdvance || busyId === piece.id}
                                    title={t('frontend.clients.repertoire.advance_title', 'Продвинуть вперёд')}
                                    aria-label={t('frontend.clients.repertoire.advance_aria', 'Продвинуть «%title%» вперёд', { title: piece.title })}
                                    onClick={() => move(piece, true)}
                                >
                                    →
                                </button>
                                <button
                                    type="button"
                                    className="notes__link-btn"
                                    onClick={() => { setNoteEditId(piece.id); setNoteDraft(piece.note ?? ''); }}
                                >
                                    {piece.note ? t('frontend.clients.repertoire.note_label', 'Заметка') : t('frontend.clients.repertoire.note_add', '+ заметка')}
                                </button>
                                <button
                                    type="button"
                                    className={`notes__link-btn${confirmDeleteId === piece.id ? ' notes__link-btn--danger' : ''}`}
                                    disabled={busyId === piece.id}
                                    onClick={() => remove(piece)}
                                >
                                    {confirmDeleteId === piece.id ? t('frontend.clients.repertoire.confirm_delete', 'Точно удалить?') : t('frontend.clients.repertoire.delete', 'Удалить')}
                                </button>
                            </div>

                            {noteEditId === piece.id ? (
                                <div className="rep__note-edit">
                                    <input
                                        className="field__input"
                                        placeholder={t('frontend.clients.repertoire.note_placeholder', 'Заметка преподавателя (темп, аппликатура…)')}
                                        value={noteDraft}
                                        onChange={(e) => setNoteDraft(e.target.value)}
                                        aria-label={t('frontend.clients.repertoire.note_aria', 'Заметка к произведению')}
                                    />
                                    <Button type="button" size="sm" variant="brass" loading={busyId === piece.id} onClick={() => saveNote(piece)}>
                                        {t('frontend.clients.repertoire.save', 'Сохранить')}
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => setNoteEditId(null)}>
                                        {t('frontend.clients.repertoire.cancel', 'Отмена')}
                                    </Button>
                                </div>
                            ) : (
                                piece.note && <p className="rep__note">{piece.note}</p>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
