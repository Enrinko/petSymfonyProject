import { ChangeEvent, useState } from 'react';
import { ApiError, httpClient } from '../../services/httpClient';
import { CsvPreview, decodeCsvBuffer, parseCsvPreview } from '../../utils/csv';
import Alert from '../ui/Alert';
import Button from '../ui/Button';

interface ImportResult {
    created: number;
    updated: number;
    skipped: number;
    errors: { line: number; message: string }[];
}

interface ImportPanelProps {
    onImported: () => void;
    onClose: () => void;
}

type Policy = 'skip' | 'update';

export default function ImportPanel({ onImported, onClose }: ImportPanelProps) {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<CsvPreview | null>(null);
    const [policy, setPolicy] = useState<Policy>('skip');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<ImportResult | null>(null);

    const handleFile = async (e: ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0] ?? null;
        setFile(selected);
        setPreview(null);
        setResult(null);
        setError(null);

        if (!selected) {
            return;
        }

        try {
            const text = decodeCsvBuffer(await selected.arrayBuffer());
            setPreview(parseCsvPreview(text));
        } catch {
            setError('Не удалось прочитать файл. Убедитесь, что это CSV.');
        }
    };

    const handleImport = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setError(null);

        const form = new FormData();
        form.append('file', file);
        form.append('duplicates', policy);

        try {
            const imported = await httpClient.postForm<ImportResult>('/api/clients/import', form);
            setResult(imported);

            if (imported.created > 0 || imported.updated > 0) {
                onImported();
            }
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Не удалось импортировать файл.');
        }

        setUploading(false);
    };

    return (
        <div className="card clients__create import-panel">
            <h3 className="clients__create-title">Импорт учеников из CSV</h3>
            <p className="import-panel__hint">
                Колонки: <code>name/имя</code> (обязательно), <code>email</code>, <code>phone/телефон</code>,{' '}
                <code>comment/комментарий</code>, <code>tags/теги</code> (через «;»). Из Excel — «Сохранить как CSV».
                Кодировки UTF-8 и Windows-1251 распознаются автоматически.
            </p>

            <input
                type="file"
                accept=".csv,text/csv"
                onChange={handleFile}
                aria-label="Файл CSV для импорта"
            />

            {preview && result === null && (
                <>
                    <div className="import-panel__preview">
                        <table className="clients-table import-panel__table">
                            <thead>
                                <tr>
                                    {preview.headers.map((h, i) => (
                                        <th key={i}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.rows.map((row, i) => (
                                    <tr key={i}>
                                        {preview.headers.map((_, j) => (
                                            <td key={j}>{row[j] ?? ''}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="import-panel__count">
                        Строк с данными: <strong>{preview.totalDataRows}</strong>
                        {preview.totalDataRows > preview.rows.length && ` (показаны первые ${preview.rows.length})`}
                    </p>

                    <div className="import-panel__policy" role="radiogroup" aria-label="Что делать с дублями по email">
                        <label>
                            <input
                                type="radio"
                                name="duplicates"
                                checked={policy === 'skip'}
                                onChange={() => setPolicy('skip')}
                            />
                            <span>дубли по email пропускать</span>
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="duplicates"
                                checked={policy === 'update'}
                                onChange={() => setPolicy('update')}
                            />
                            <span>дубли обновлять данными из файла</span>
                        </label>
                    </div>
                </>
            )}

            {error && <Alert kind="error">{error}</Alert>}

            {result && (
                <div className="import-panel__result">
                    <Alert kind={result.errors.length === 0 ? 'success' : 'error'}>
                        Создано: {result.created} · Обновлено: {result.updated} · Пропущено: {result.skipped}
                        {result.errors.length > 0 && ` · Ошибок: ${result.errors.length}`}
                    </Alert>
                    {result.errors.length > 0 && (
                        <ul className="import-panel__errors">
                            {result.errors.map((err) => (
                                <li key={`${err.line}-${err.message}`}>
                                    <strong>Строка {err.line}:</strong> {err.message}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            <div className="clients__create-actions">
                {result === null && (
                    <Button
                        type="button"
                        variant="brass"
                        loading={uploading}
                        disabled={!file || !preview}
                        onClick={handleImport}
                    >
                        {uploading ? 'Импортируем…' : 'Импортировать'}
                    </Button>
                )}
                <Button type="button" variant="ghost" onClick={onClose}>
                    {result === null ? 'Отмена' : 'Закрыть'}
                </Button>
            </div>
        </div>
    );
}
