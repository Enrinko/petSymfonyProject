<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Адаптер порта аудита: актора берёт из security-токена,
 * IP и user agent — из текущего запроса.
 *
 * Ошибка записи журнала не роняет бизнес-операцию: аудит — наблюдатель,
 * а не участник транзакции; сбой уходит в обычный лог.
 */
final readonly class RequestAwareAuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $events,
        private RequestStack $requestStack,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    public function log(
        AuditAction $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
    ): void {
        $request = $this->requestStack->getMainRequest();
        $actor = $this->security->getUser();
        $actor = $actor instanceof User ? $actor : null;

        $event = AuditEvent::record(
            $action,
            $actor?->getId(),
            $actor?->getEmail(),
            $subjectType,
            $subjectId,
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
            $payload,
        );

        try {
            $this->events->save($event);
        } catch (\Throwable $e) {
            $this->logger->error('Audit write failed.', ['exception' => $e, 'action' => $action->value]);
        }
    }
}
