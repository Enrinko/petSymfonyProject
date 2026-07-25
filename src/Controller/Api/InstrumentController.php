<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Instrument\CreateInstrumentCommand;
use App\Application\Instrument\CreateInstrumentHandler;
use App\Application\Instrument\UpdateInstrumentCommand;
use App\Application\Instrument\UpdateInstrumentHandler;
use App\Domain\Instrument\Exception\InstrumentNameTakenException;
use App\Domain\Instrument\Exception\InstrumentNotFoundException;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use App\Domain\Instrument\InstrumentRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstrumentController extends AbstractController
{
    /**
     * Плоский справочник для селектов + список категорий с человекочитаемыми именами.
     */
    #[Route('/api/instruments', name: 'api_instruments_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(InstrumentRepositoryInterface $instruments): JsonResponse
    {
        return $this->json([
            'data' => array_map(self::view(...), $instruments->findAll()),
            'categories' => array_map(
                static fn (InstrumentCategory $c): array => ['value' => $c->value, 'label' => $c->label()],
                InstrumentCategory::cases(),
            ),
        ]);
    }

    #[Route('/api/admin/instruments', name: 'api_admin_instruments_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        CreateInstrumentHandler $handler,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new CreateInstrumentCommand(
            (string) ($payload['name'] ?? ''),
            (string) ($payload['category'] ?? ''),
            (int) ($payload['sortOrder'] ?? 0),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $instrument = $handler($command);
        } catch (InstrumentNameTakenException) {
            return ApiJson::error(
                'Такой инструмент уже есть в справочнике.',
                Response::HTTP_CONFLICT,
                ['name' => 'Такой инструмент уже есть в справочнике.'],
            );
        }

        return $this->json(self::view($instrument), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/instruments/{id}', name: 'api_admin_instruments_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        int $id,
        Request $request,
        ValidatorInterface $validator,
        UpdateInstrumentHandler $handler,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new UpdateInstrumentCommand(
            $id,
            (string) ($payload['name'] ?? ''),
            (string) ($payload['category'] ?? ''),
            (int) ($payload['sortOrder'] ?? 0),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $instrument = $handler($command);
        } catch (InstrumentNotFoundException) {
            return ApiJson::error('api.instrument.not_found', Response::HTTP_NOT_FOUND);
        } catch (InstrumentNameTakenException) {
            return ApiJson::error(
                'Такой инструмент уже есть в справочнике.',
                Response::HTTP_CONFLICT,
                ['name' => 'Такой инструмент уже есть в справочнике.'],
            );
        }

        return $this->json(self::view($instrument));
    }

    #[Route('/api/admin/instruments/{id}', name: 'api_admin_instruments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, InstrumentRepositoryInterface $instruments): JsonResponse
    {
        $instrument = $instruments->find($id);

        if ($instrument === null) {
            return ApiJson::error('api.instrument.not_found', Response::HTTP_NOT_FOUND);
        }

        $instruments->remove($instrument);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{id: int, name: string, category: string, categoryLabel: string, sortOrder: int}
     */
    private static function view(Instrument $instrument): array
    {
        return [
            'id' => (int) $instrument->getId(),
            'name' => $instrument->getName(),
            'category' => $instrument->getCategory()->value,
            'categoryLabel' => $instrument->getCategory()->label(),
            'sortOrder' => $instrument->getSortOrder(),
        ];
    }
}
