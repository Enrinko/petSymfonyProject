import { httpClient } from './httpClient';

export interface Profile {
    email: string;
    displayName: string | null;
    initials: string;
    avatarUrl: string | null;
    roles: string[];
    createdAt: string;
    totpEnabled: boolean;
}

export interface TwoFactorSetup {
    secret: string;
    otpauthUri: string;
}

export interface ProfileSession {
    id: number;
    browser: string;
    os: string;
    ip: string | null;
    createdAt: string;
    lastSeenAt: string;
    current: boolean;
}

export class ProfileApiService {
    getProfile(): Promise<Profile> {
        return httpClient.get<Profile>('/api/profile');
    }

    updateProfile(displayName: string): Promise<Profile> {
        return httpClient.patch<Profile>('/api/profile', { displayName });
    }

    setup2fa(): Promise<TwoFactorSetup> {
        return httpClient.post<TwoFactorSetup>('/api/profile/2fa/setup');
    }

    enable2fa(code: string): Promise<{ message: string; backupCodes: string[] }> {
        return httpClient.post<{ message: string; backupCodes: string[] }>('/api/profile/2fa/enable', { code });
    }

    disable2fa(currentPassword: string, code: string): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>('/api/profile/2fa/disable', { currentPassword, code });
    }

    changePassword(currentPassword: string, newPassword: string, newPasswordConfirm: string): Promise<{ message: string }> {
        return httpClient.post<{ message: string }>('/api/profile/password', {
            currentPassword,
            newPassword,
            newPasswordConfirm,
        });
    }

    uploadAvatar(file: File): Promise<Profile> {
        const form = new FormData();
        form.append('avatar', file);

        return httpClient.postForm<Profile>('/api/profile/avatar', form);
    }

    deleteAvatar(): Promise<Profile> {
        return httpClient.del<Profile>('/api/profile/avatar');
    }

    getSessions(): Promise<{ sessions: ProfileSession[] }> {
        return httpClient.get<{ sessions: ProfileSession[] }>('/api/profile/sessions');
    }

    terminateSession(id: number): Promise<{ message: string }> {
        return httpClient.del<{ message: string }>(`/api/profile/sessions/${id}`);
    }

    terminateOtherSessions(): Promise<{ message: string; terminated: number }> {
        return httpClient.del<{ message: string; terminated: number }>('/api/profile/sessions');
    }
}
