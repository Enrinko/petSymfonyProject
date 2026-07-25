import { httpClient } from './httpClient';

export interface RegisterFields {
    email: string;
    password: string;
    passwordConfirm: string;
}

export interface AuthenticatedUser {
    id: number;
    email: string;
    roles: string[];
}

export interface LoginResponse {
    user?: AuthenticatedUser;
    /** Пароль верен, но включена 2FA — нужен второй шаг с кодом. */
    twoFactorRequired?: boolean;
}

export class AuthApiService {
    constructor(
        private readonly loginUrl: string = '/api/login',
        private readonly registerUrl: string = '/api/register',
    ) {}

    login(
        email: string,
        password: string,
        csrfToken: string,
        rememberMe: boolean = false,
    ): Promise<LoginResponse> {
        return httpClient.post<LoginResponse>(
            this.loginUrl,
            { email, password, _csrf_token: csrfToken, _remember_me: rememberMe },
            { skipAuthRedirect: true },
        );
    }

    /** Второй шаг входа: код TOTP или резервный. 401 = неверный код. */
    submitTwoFactorCode(code: string): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>('/2fa_check', { _auth_code: code }, { skipAuthRedirect: true });
    }

    register(fields: RegisterFields): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>(this.registerUrl, fields, { skipAuthRedirect: true });
    }
}
