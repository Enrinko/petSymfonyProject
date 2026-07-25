<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Session\UserAgentSummary;
use App\Domain\Session\UserSession;
use App\Domain\Session\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Активные сессии пользователя. Доступ — только полная аутентификация
 * (access_control): угнанная remember-кука не должна управлять сессиями.
 */
#[Route('/api/profile/sessions')]
final class ProfileSessionsController extends AbstractController
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $sessions,
    ) {
    }

    #[Route('', name: 'api_profile_sessions', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $currentHash = $this->currentHash($request);

        $items = array_map(
            static function (UserSession $session) use ($currentHash): array {
                $summary = UserAgentSummary::parse($session->getUserAgent());

                return [
                    'id' => $session->getId(),
                    'browser' => $summary->browser,
                    'os' => $summary->os,
                    'ip' => $session->getIp(),
                    'createdAt' => $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'lastSeenAt' => $session->getLastSeenAt()->format(\DateTimeInterface::ATOM),
                    'current' => $session->getSessionIdHash() === $currentHash,
                ];
            },
            $this->sessions->findByUser((int) $user->getId()),
        );

        return $this->json(['sessions' => $items]);
    }

    #[Route('/{id}', name: 'api_profile_sessions_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function terminate(int $id, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        $target = $this->findOwnSession($id, $user);

        if ($target === null) {
            return ApiJson::error('api.session.not_found', Response::HTTP_NOT_FOUND);
        }

        if ($target->getSessionIdHash() === $this->currentHash($request)) {
            return ApiJson::error(
                'Текущую сессию так не завершить — используйте «Выйти».',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['session' => 'Это текущая сессия.'],
            );
        }

        $this->sessions->removeByHash($target->getSessionIdHash());

        return $this->json(['message' => 'api.session.terminated']);
    }

    #[Route('', name: 'api_profile_sessions_delete_all', methods: ['DELETE'])]
    public function terminateOthers(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $terminated = $this->sessions->removeAllForUserExcept(
            (int) $user->getId(),
            $this->currentHash($request),
        );

        return $this->json(['message' => 'api.session.others_terminated', 'terminated' => $terminated]);
    }

    private function currentHash(Request $request): string
    {
        return UserSession::hashOf($request->getSession()->getId());
    }

    private function findOwnSession(int $id, User $user): ?UserSession
    {
        foreach ($this->sessions->findByUser((int) $user->getId()) as $session) {
            if ($session->getId() === $id) {
                return $session;
            }
        }

        // Чужая сессия по id намеренно неотличима от несуществующей
        return null;
    }
}
