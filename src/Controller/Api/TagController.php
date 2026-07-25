<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Tag\TagRepositoryInterface;
use App\Domain\Tag\TagUsage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TagController extends AbstractController
{
    /**
     * Все теги со счётчиком использований — для автодополнения и фильтра.
     */
    #[Route('/api/tags', name: 'api_tags_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(TagRepositoryInterface $tags): JsonResponse
    {
        return $this->json([
            'data' => array_map(
                static fn (TagUsage $usage): array => [
                    'id' => (int) $usage->tag->getId(),
                    'name' => $usage->tag->getName(),
                    'usageCount' => $usage->usageCount,
                ],
                $tags->findAllWithUsage(),
            ),
        ]);
    }

    /**
     * Удаление тега отовсюду (связи client_tag чистятся каскадом join-таблицы).
     */
    #[Route('/api/admin/tags/{id}', name: 'api_admin_tags_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, TagRepositoryInterface $tags): JsonResponse
    {
        $tag = $tags->find($id);

        if ($tag === null) {
            return ApiJson::error('api.tag.not_found', Response::HTTP_NOT_FOUND);
        }

        $tags->remove($tag);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
