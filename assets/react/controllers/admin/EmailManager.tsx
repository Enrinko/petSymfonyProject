import { useCallback, useEffect, useMemo, useState } from 'react';
import { t } from '../../i18n';
import { EmailApiService, EmailTemplateContent, EmailTemplateListItem } from '../../services/EmailApiService';
import { ApiError } from '../../services/httpClient';
import Alert from '../../components/ui/Alert';
import Button from '../../components/ui/Button';

const templateName = (key: string): string => {
    switch (key) {
        case 'password_reset': return t('frontend.admin.emails.name_password_reset', 'Сброс пароля');
        case 'verify_email': return t('frontend.admin.emails.name_verify_email', 'Подтверждение email');
        case 'lesson_reminder': return t('frontend.admin.emails.name_lesson_reminder', 'Напоминание о занятии');
        default: return key;
    }
};

export default function EmailManager() {
    const [items, setItems] = useState<EmailTemplateListItem[]>([]);
    const [selected, setSelected] = useState<{ key: string; locale: string } | null>(null);
    const [content, setContent] = useState<EmailTemplateContent | null>(null);
    const [form, setForm] = useState({ subject: '', bodyHtml: '', bodyText: '' });
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);

    const api = useMemo(() => new EmailApiService(), []);

    const loadList = useCallback(async () => {
        try {
            setItems((await api.list()).data);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.admin.emails.load_error', 'Не удалось загрузить письма.'));
        }
    }, [api]);

    useEffect(() => {
        void loadList();
    }, [loadList]);

    const openTemplate = async (key: string, locale: string) => {
        setSelected({ key, locale });
        setContent(null);
        setPreviewHtml(null);
        setSaved(false);
        setError(null);
        setLoading(true);

        try {
            const c = await api.get(key, locale);
            setContent(c);
            setForm({ subject: c.subject, bodyHtml: c.bodyHtml, bodyText: c.bodyText });
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.admin.emails.load_error', 'Не удалось загрузить письма.'));
        }

        setLoading(false);
    };

    const doPreview = async () => {
        if (!selected) {
            return;
        }

        setError(null);

        try {
            setPreviewHtml((await api.preview(selected.key, selected.locale, form)).html);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : t('frontend.admin.emails.preview_error', 'Не удалось построить предпросмотр.'));
        }
    };

    const save = async () => {
        if (!selected) {
            return;
        }

        setSaving(true);
        setError(null);
        setSaved(false);

        try {
            await api.update(selected.key, selected.locale, form);
            setSaved(true);
            await loadList();
        } catch (err) {
            setError(err instanceof ApiError
                ? (err.errors ? Object.values(err.errors).join(' ') : err.message)
                : t('frontend.admin.emails.save_error', 'Не удалось сохранить.'));
        }

        setSaving(false);
    };

    // Список сгруппирован по ключу; локали идут парой RU/EN
    const grouped = useMemo(() => {
        const map = new Map<string, EmailTemplateListItem[]>();
        items.forEach((it) => {
            const arr = map.get(it.key) ?? [];
            arr.push(it);
            map.set(it.key, arr);
        });
        return Array.from(map.entries());
    }, [items]);

    return (
        <div className="emails-admin">
            {error && <Alert kind="error">{error}</Alert>}

            <div className="emails-admin__layout">
                <aside className="emails-admin__list card">
                    {grouped.map(([key, locales]) => (
                        <div key={key} className="emails-admin__group">
                            <span className="emails-admin__group-title">{templateName(key)}</span>
                            <div className="emails-admin__locales">
                                {locales.map((it) => {
                                    const active = selected?.key === it.key && selected?.locale === it.locale;
                                    return (
                                        <button
                                            key={it.locale}
                                            type="button"
                                            className={`emails-admin__locale${active ? ' emails-admin__locale--active' : ''}`}
                                            onClick={() => openTemplate(it.key, it.locale)}
                                        >
                                            {it.locale.toUpperCase()}
                                            {it.customized && (
                                                <span className="emails-admin__badge" title={t('frontend.admin.emails.customized', 'Изменено')}>●</span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </aside>

                <section className="emails-admin__editor card">
                    {!selected && (
                        <p className="emails-admin__hint">{t('frontend.admin.emails.select_hint', 'Выберите письмо и язык слева.')}</p>
                    )}

                    {selected && loading && <p aria-busy="true">{t('frontend.common.loading', 'Загружаем…')}</p>}

                    {selected && content && !loading && (
                        <>
                            <label className="field">
                                <span className="field__label">{t('frontend.admin.emails.subject', 'Тема')}</span>
                                <input
                                    className="field__input"
                                    value={form.subject}
                                    onChange={(e) => { setForm((f) => ({ ...f, subject: e.currentTarget.value })); setSaved(false); }}
                                />
                            </label>

                            <label className="field">
                                <span className="field__label">{t('frontend.admin.emails.body_html', 'HTML-тело')}</span>
                                <textarea
                                    className="field__input emails-admin__code"
                                    rows={12}
                                    value={form.bodyHtml}
                                    onChange={(e) => { setForm((f) => ({ ...f, bodyHtml: e.currentTarget.value })); setSaved(false); }}
                                />
                            </label>

                            <label className="field">
                                <span className="field__label">{t('frontend.admin.emails.body_text', 'Текстовое тело')}</span>
                                <textarea
                                    className="field__input emails-admin__code"
                                    rows={6}
                                    value={form.bodyText}
                                    onChange={(e) => { setForm((f) => ({ ...f, bodyText: e.currentTarget.value })); setSaved(false); }}
                                />
                            </label>

                            {content.placeholders.length > 0 && (
                                <p className="emails-admin__placeholders">
                                    {t('frontend.admin.emails.placeholders', 'Плейсхолдеры:')}{' '}
                                    {content.placeholders.map((p) => <code key={p}>%{p}%</code>).reduce((a, b) => <>{a} {b}</>)}
                                </p>
                            )}

                            <div className="emails-admin__actions">
                                <Button type="button" variant="brass" loading={saving} onClick={save}>
                                    {t('frontend.admin.emails.save', 'Сохранить')}
                                </Button>
                                <Button type="button" variant="ghost" onClick={doPreview}>
                                    {t('frontend.admin.emails.preview', 'Предпросмотр')}
                                </Button>
                                {saved && <span className="emails-admin__saved">{t('frontend.admin.emails.saved', 'Сохранено ✓')}</span>}
                            </div>

                            {previewHtml !== null && (
                                <iframe
                                    className="emails-admin__preview"
                                    title={t('frontend.admin.emails.preview', 'Предпросмотр')}
                                    srcDoc={previewHtml}
                                />
                            )}
                        </>
                    )}
                </section>
            </div>
        </div>
    );
}
