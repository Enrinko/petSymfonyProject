<?php

declare(strict_types=1);

namespace App\Domain\Audit;

enum AuditAction: string
{
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case LoggedOut = 'login.logged_out';
    case RolesChanged = 'user.roles_changed';
    case UserDeactivated = 'user.deactivated';
    case UserActivated = 'user.activated';
    case EmailVerified = 'email.verified';
    case PasswordChanged = 'password.changed';
    case PasswordResetRequested = 'password.reset_requested';
    case PasswordResetCompleted = 'password.reset_completed';
    case TwoFactorEnabled = '2fa.enabled';
    case TwoFactorDisabled = '2fa.disabled';
    case TwoFactorFailed = '2fa.failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
