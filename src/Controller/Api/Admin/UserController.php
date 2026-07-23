<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Application\User\ChangeUserRolesCommand;
use App\Application\User\ChangeUserRolesHandler;
use App\Application\User\ListUsersHandler;
use App\Application\User\ListUsersQuery;
use App\Application\User\UserView;
use App\Controller\Api\ApiJson;
use App\Domain\User\Exception\CannotRemoveOwnAdminRoleException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Security\Voter\UserVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(Request $request, ListUsersHandler $handler): JsonResponse
    {
        $page = $handler(new ListUsersQuery(
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', 20),
            (string) $request->query->get('search', ''),
        ));

        return $this->json($page);
    }

    #[Route('/{id}/roles', name: 'api_admin_users_roles', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateRoles(
        int $id,
        Request $request,
        UserRepositoryInterface $users,
        ValidatorInterface $validator,
        ChangeUserRolesHandler $handler,
    ): JsonResponse {
        $target = $users->findById($id);

        if ($target === null) {
            return ApiJson::error('Пользователь не найден.', Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(UserVoter::MANAGE_ROLES, $target);

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !\is_array($payload['roles'] ?? null)) {
            return ApiJson::error('Тело запроса должно содержать массив roles.', Response::HTTP_BAD_REQUEST);
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $command = new ChangeUserRolesCommand(
            $id,
            array_values(array_map(strval(...), $payload['roles'])),
            (int) $actor->getId(),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $user = $handler($command);
        } catch (CannotRemoveOwnAdminRoleException) {
            return ApiJson::error(
                'Нельзя снять роль администратора с самого себя.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['roles' => 'Нельзя снять роль администратора с самого себя.'],
            );
        }

        return $this->json(UserView::fromUser($user));
    }
}
