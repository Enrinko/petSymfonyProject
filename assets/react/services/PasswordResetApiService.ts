import { httpClient } from './httpClient';

export class PasswordResetApiService {
    constructor(
        private readonly requestUrl: string = '/api/password/forgot',
        private readonly resetUrl: string = '/api/password/reset',
    ) {}

    requestReset(email: string): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>(this.requestUrl, { email }, { skipAuthRedirect: true });
    }

    performReset(token: string, password: string, passwordConfirm: string): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>(
            this.resetUrl,
            { token, password, passwordConfirm },
            { skipAuthRedirect: true },
        );
    }
}
