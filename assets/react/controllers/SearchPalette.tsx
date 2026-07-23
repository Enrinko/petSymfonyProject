import { KeyboardEvent as ReactKeyboardEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ClientHit, NoteHit, SearchApiService, TagHit } from '../services/SearchApiService';
import { isAborted } from '../services/httpClient';
import { tagColorIndex } from '../utils/tagColor';

interface SearchPaletteProps {
    clientsBasePath: string;
}

type Item =
    | { kind: 'client'; hit: ClientHit }
    | { kind: 'tag'; hit: TagHit }
    | { kind: 'note'; hit: NoteHit };

function pluralStudents(n: number): string {
    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'ученик';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'ученика';
    }

    return 'учеников';
}

export default function SearchPalette({ clientsBasePath }: SearchPaletteProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [clients, setClients] = useState<ClientHit[]>([]);
    const [tags, setTags] = useState<TagHit[]>([]);
    const [notes, setNotes] = useState<NoteHit[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);

    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const apiService = useMemo(() => new SearchApiService(), []);

    // Плоский список для клавиатурной навигации: клиенты, теги, заметки
    const items: Item[] = useMemo(
        () => [
            ...clients.map((hit): Item => ({ kind: 'client', hit })),
            ...tags.map((hit): Item => ({ kind: 'tag', hit })),
            ...notes.map((hit): Item => ({ kind: 'note', hit })),
        ],
        [clients, tags, notes],
    );

    const close = useCallback(() => {
        setOpen(false);
        setQuery('');
        setClients([]);
        setTags([]);
        setNotes([]);
        setActive(0);
        abortRef.current?.abort();
    }, []);

    const go = useCallback((item: Item) => {
        if (item.kind === 'tag') {
            // Тег → список учеников, отфильтрованный по этому тегу
            window.location.assign(`${clientsBasePath}?tags=${encodeURIComponent(item.hit.name)}`);
            return;
        }

        const id = item.kind === 'client' ? item.hit.id : item.hit.clientId;
        window.location.assign(`${clientsBasePath}/${id}`);
    }, [clientsBasePath]);

    // Глобальный хоткей Ctrl/Cmd+K и клик по кнопке [data-palette-trigger] в топбаре
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            // e.code — физическая клавиша, не зависит от раскладки:
            // на русской «K» даёт e.key === 'л', а e.code остаётся 'KeyK'.
            if ((e.ctrlKey || e.metaKey) && e.code === 'KeyK') {
                e.preventDefault();
                setOpen((v) => !v);
            }
        };

        const onClick = (e: MouseEvent) => {
            if ((e.target as HTMLElement).closest('[data-palette-trigger]')) {
                setOpen(true);
            }
        };

        window.addEventListener('keydown', onKey);
        document.addEventListener('click', onClick);
        return () => {
            window.removeEventListener('keydown', onKey);
            document.removeEventListener('click', onClick);
        };
    }, []);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    // Поиск с debounce и отменой предыдущего запроса
    useEffect(() => {
        if (!open) {
            return;
        }

        const trimmed = query.trim();

        if (trimmed.length < 2) {
            setClients([]);
            setNotes([]);
            setLoading(false);
            return;
        }

        const timer = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setLoading(true);

            try {
                const results = await apiService.search(trimmed, controller.signal);
                setClients(results.clients);
                setTags(results.tags);
                setNotes(results.notes);
                setActive(0);
                setLoading(false);
            } catch (err) {
                if (!isAborted(err)) {
                    setLoading(false);
                }
            }
        }, 200);

        return () => clearTimeout(timer);
    }, [query, open, apiService]);

    const onInputKeyDown = (e: ReactKeyboardEvent) => {
        if (e.key === 'Escape') {
            close();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter' && items[active]) {
            e.preventDefault();
            go(items[active]);
        }
    };

    if (!open) {
        return null;
    }

    const hasQuery = query.trim().length >= 2;

    return (
        <div className="palette" role="dialog" aria-modal="true" aria-label="Поиск" onMouseDown={close}>
            <div className="palette__box" onMouseDown={(e) => e.stopPropagation()}>
                <div className="palette__search">
                    <span className="palette__icon" aria-hidden="true">⌕</span>
                    <input
                        ref={inputRef}
                        type="text"
                        className="palette__input"
                        placeholder="Поиск учеников и заметок…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={onInputKeyDown}
                        aria-label="Поисковый запрос"
                        aria-activedescendant={items[active] ? `palette-item-${active}` : undefined}
                    />
                    <kbd className="palette__esc">esc</kbd>
                </div>

                <div className="palette__results">
                    {loading && <div className="palette__hint">Ищем…</div>}

                    {!loading && hasQuery && items.length === 0 && (
                        <div className="palette__hint">Ничего не нашли по запросу «{query.trim()}».</div>
                    )}

                    {!loading && !hasQuery && (
                        <div className="palette__hint">Введите минимум 2 символа. Ctrl+K — открыть/закрыть.</div>
                    )}

                    {clients.length > 0 && (
                        <div className="palette__group">
                            <div className="palette__group-title">Ученики</div>
                            {clients.map((hit, i) => (
                                <button
                                    key={`c-${hit.id}`}
                                    id={`palette-item-${i}`}
                                    type="button"
                                    className={`palette__item${active === i ? ' palette__item--active' : ''}`}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => go({ kind: 'client', hit })}
                                >
                                    <span className="palette__item-title">
                                        {hit.name}
                                        {hit.archived && <span className="badge palette__badge">архив</span>}
                                    </span>
                                    <span className="palette__item-sub">{hit.email ?? hit.phone ?? ''}</span>
                                </button>
                            ))}
                        </div>
                    )}

                    {tags.length > 0 && (
                        <div className="palette__group">
                            <div className="palette__group-title">Теги</div>
                            {tags.map((hit, i) => {
                                const index = clients.length + i;
                                return (
                                    <button
                                        key={`t-${hit.name}`}
                                        id={`palette-item-${index}`}
                                        type="button"
                                        className={`palette__item${active === index ? ' palette__item--active' : ''}`}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => go({ kind: 'tag', hit })}
                                    >
                                        <span className="palette__item-title">
                                            <span className={`tag-chip tag-chip--sm tag-chip--c${tagColorIndex(hit.name)}`}>
                                                {hit.name}
                                            </span>
                                        </span>
                                        <span className="palette__item-sub">
                                            {hit.usageCount} {pluralStudents(hit.usageCount)} · показать всех
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {notes.length > 0 && (
                        <div className="palette__group">
                            <div className="palette__group-title">Заметки</div>
                            {notes.map((hit, i) => {
                                const index = clients.length + tags.length + i;
                                return (
                                    <button
                                        key={`n-${hit.id}`}
                                        id={`palette-item-${index}`}
                                        type="button"
                                        className={`palette__item${active === index ? ' palette__item--active' : ''}`}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => go({ kind: 'note', hit })}
                                    >
                                        <span className="palette__item-title">{hit.clientName}</span>
                                        <span className="palette__item-sub">{hit.snippet}</span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
