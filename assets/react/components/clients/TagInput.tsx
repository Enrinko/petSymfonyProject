import { KeyboardEvent, useState } from 'react';
import { tagColorIndex } from '../../utils/tagColor';
import { t } from '../../i18n';

interface TagInputProps {
    id: string;
    label: string;
    value: string[];
    onChange: (tags: string[]) => void;
    suggestions: string[];
    error?: string;
}

const MAX_TAGS = 20;
const MAX_TAG_LENGTH = 40;

/**
 * Чипы + текстовый ввод: Enter или запятая добавляет тег,
 * Backspace на пустом поле снимает последний. Подсказки — нативный datalist.
 */
export default function TagInput({ id, label, value, onChange, suggestions, error }: TagInputProps) {
    const [draft, setDraft] = useState('');

    const addDraft = () => {
        const name = draft.trim().toLowerCase().slice(0, MAX_TAG_LENGTH);

        if (name === '' || value.includes(name) || value.length >= MAX_TAGS) {
            setDraft('');
            return;
        }

        onChange([...value, name]);
        setDraft('');
    };

    const removeTag = (name: string) => {
        onChange(value.filter((tag) => tag !== name));
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addDraft();
        }

        if (e.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    };

    const datalistId = `${id}-suggestions`;
    const available = suggestions.filter((s) => !value.includes(s));

    return (
        <div className="field">
            <label className="field__label" htmlFor={id}>{label}</label>
            <div className={`tag-input${error ? ' tag-input--invalid' : ''}`}>
                {value.map((tag) => (
                    <span key={tag} className={`tag-chip tag-chip--c${tagColorIndex(tag)}`}>
                        {tag}
                        <button
                            type="button"
                            className="tag-chip__remove"
                            aria-label={t('frontend.clients.tags.remove_tag', 'Убрать тег %tag%', { tag })}
                            onClick={() => removeTag(tag)}
                        >
                            ×
                        </button>
                    </span>
                ))}
                <input
                    id={id}
                    className="tag-input__field"
                    type="text"
                    list={datalistId}
                    placeholder={value.length === 0 ? t('frontend.clients.tags.placeholder', 'вокал, подготовка к конкурсу…') : ''}
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={addDraft}
                    aria-describedby={error ? `${id}-error` : undefined}
                />
                <datalist id={datalistId}>
                    {available.map((s) => (
                        <option key={s} value={s} />
                    ))}
                </datalist>
            </div>
            {error && <span className="field__error" id={`${id}-error`}>{error}</span>}
        </div>
    );
}
