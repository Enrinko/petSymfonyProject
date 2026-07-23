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

    login(email: string, password: string, csrfToken: string): Promise<LoginResponse> {
        return httpClient.post<LoginResponse>(
            this.loginUrl,
            { email, password, _csrf_token: csrfToken },
            { skipAuthRedirect: true },
        );
    }

    register(fields: RegisterFields): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>(this.registerUrl, fields, { skipAuthRedirect: true });
    }
}
