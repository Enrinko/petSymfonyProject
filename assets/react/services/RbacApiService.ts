import { httpClient } from './httpClient';

export type UserRole = 'ROLE_USER' | 'ROLE_MODERATOR' | 'ROLE_ADMIN';

export interface AdminUser {
    id: number;
    email: string;
    roles: UserRole[];
    createdAt: string;
}

export interface UsersPage {
    users: AdminUser[];
    total: number;
    page: number;
    perPage: number;
}

export class RbacApiService {
    getUsers(page = 1, search = '', perPage = 20): Promise<UsersPage> {
        const params = new URLSearchParams({
            page: String(page),
            perPage: String(perPage),
            search,
        });

        return httpClient.get<UsersPage>(`/api/admin/users?${params}`);
    }

    updateRoles(userId: number, roles: UserRole[]): Promise<AdminUser> {
        return httpClient.patch<AdminUser>(`/api/admin/users/${userId}/roles`, { roles });
    }
}
