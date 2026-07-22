/**
 * Утилиты предпросмотра CSV на клиенте. Только для показа первых строк —
 * источник истины по разбору и кодировкам остаётся сервер.
 */

export interface CsvPreview {
    headers: string[];
    rows: string[][];
    totalDataRows: number;
    delimiter: string;
}

/** UTF-8 (строгий), при провале — Windows-1251. */
export function decodeCsvBuffer(buffer: ArrayBuffer): string {
    try {
        return new TextDecoder('utf-8', { fatal: true }).decode(buffer);
    } catch {
        return new TextDecoder('windows-1251').decode(buffer);
    }
}

function detectDelimiter(firstLine: string): string {
    return (firstLine.split(';').length - 1) > (firstLine.split(',').length - 1) ? ';' : ',';
}

/** Минимальный RFC 4180-разбор: кавычки, экранированные "" и переносы внутри ячеек. */
function parseCsvText(text: string, delimiter: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const ch = text[i];

        if (inQuotes) {
            if (ch === '"' && text[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (ch === '"') {
                inQuotes = false;
            } else {
                cell += ch;
            }
            continue;
        }

        if (ch === '"') {
            inQuotes = true;
        } else if (ch === delimiter) {
            row.push(cell);
            cell = '';
        } else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') {
                i++;
            }
            row.push(cell);
            cell = '';
            rows.push(row);
            row = [];
        } else {
            cell += ch;
        }
    }

    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }

    return rows.filter((r) => r.some((c) => c.trim() !== ''));
}

export function parseCsvPreview(text: string, maxRows = 10): CsvPreview {
    const clean = text.startsWith('﻿') ? text.slice(1) : text;
    const delimiter = detectDelimiter(clean.split(/\r?\n/, 1)[0] ?? '');
    const parsed = parseCsvText(clean, delimiter);

    const headers = parsed[0] ?? [];
    const dataRows = parsed.slice(1);

    return {
        headers,
        rows: dataRows.slice(0, maxRows),
        totalDataRows: dataRows.length,
        delimiter,
    };
}
