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
    user: AuthenticatedUser;
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

    register(fields: RegisterFields): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>(this.registerUrl, fields, { skipAuthRedirect: true });
    }
}
