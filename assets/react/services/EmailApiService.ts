import { httpClient } from './httpClient';

export interface EmailTemplateListItem {
    key: string;
    locale: string;
    subject: string;
    customized: boolean;
    updatedAt: string | null;
}

export interface EmailTemplateContent {
    key: string;
    locale: string;
    subject: string;
    bodyHtml: string;
    bodyText: string;
    placeholders: string[];
    customized: boolean;
}

export interface EmailTemplateInput {
    subject: string;
    bodyHtml: string;
    bodyText: string;
}

export interface EmailPreview {
    subject: string;
    html: string;
}

export class EmailApiService {
    list(): Promise<{ data: EmailTemplateListItem[] }> {
        return httpClient.get('/api/admin/emails');
    }

    get(key: string, locale: string): Promise<EmailTemplateContent> {
        return httpClient.get(`/api/admin/emails/${key}/${locale}`);
    }

    update(key: string, locale: string, input: EmailTemplateInput): Promise<{ status: string; updatedAt: string }> {
        return httpClient.put(`/api/admin/emails/${key}/${locale}`, input);
    }

    preview(key: string, locale: string, input: EmailTemplateInput): Promise<EmailPreview> {
        return httpClient.post(`/api/admin/emails/${key}/${locale}/preview`, input);
    }
}
