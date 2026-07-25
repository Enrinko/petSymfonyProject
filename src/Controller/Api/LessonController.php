<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Lesson\CancelLessonHandler;
use App\Application\Lesson\CompleteLessonHandler;
use App\Application\Lesson\LessonView;
use App\Application\Lesson\MissLessonHandler;
use App\Application\Lesson\RescheduleLessonCommand;
use App\Application\Lesson\RescheduleLessonHandler;
use App\Application\Lesson\ScheduleLessonCommand;
use App\Application\Lesson\ScheduleLessonHandler;
use App\Application\Lesson\WeekScheduleHandler;
use App\Domain\Client\Exception\ClientNotFoundException;
use App\Domain\Lesson\Exception\InvalidLessonException;
use App\Domain\Lesson\Exception\LessonNotFoundException;
use App\Domain\Lesson\Exception\LessonOverlapException;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/lessons')]
#[IsGranted('ROLE_USER')]
final class LessonController extends AbstractController
{
    private const string NOT_FOUND = 'api.lesson.not_found';
    private const string OVERLAP = 'api.lesson.overlap';

    #[Route('', name: 'api_lessons_week', methods: ['GET'])]
    public function week(Request $request, WeekScheduleHandler $handler): JsonResponse
    {
        /** @var User $teacher */
        $teacher = $this->getUser();

        $dateParam = (string) $request->query->get('date', '');

        try {
            $anchor = $dateParam !== '' ? new \DateTimeImmutable($dateParam) : new \DateTimeImmutable();
        } catch (\Exception) {
            $anchor = new \DateTimeImmutable();
        }

        return $this->json($handler($teacher, $anchor));
    }

    #[Route('', name: 'api_lessons_create', methods: ['POST'])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        ScheduleLessonHandler $handler,
    ): JsonResponse {
        /** @var User $teacher */
        $teacher = $this->getUser();

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new ScheduleLessonCommand(
            (int) ($payload['clientId'] ?? 0),
            isset($payload['instrumentId']) && is_numeric($payload['instrumentId']) ? (int) $payload['instrumentId'] : null,
            (string) ($payload['startsAt'] ?? ''),
            (int) ($payload['durationMinutes'] ?? 0),
            isset($payload['comment']) ? (string) $payload['comment'] : null,
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $lesson = $handler($command, $teacher);
        } catch (ClientNotFoundException) {
            return ApiJson::error('api.client.inaccessible', Response::HTTP_NOT_FOUND);
        } catch (LessonOverlapException) {
            return ApiJson::error(self::OVERLAP, Response::HTTP_CONFLICT);
        } catch (InvalidLessonException $e) {
            return ApiJson::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(LessonView::fromLesson($lesson), Response::HTTP_CREATED);
    }

    #[Route('/{id}/reschedule', name: 'api_lessons_reschedule', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function reschedule(
        int $id,
        Request $request,
        ValidatorInterface $validator,
        RescheduleLessonHandler $handler,
    ): JsonResponse {
        /** @var User $teacher */
        $teacher = $this->getUser();

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new RescheduleLessonCommand(
            $id,
            (string) ($payload['startsAt'] ?? ''),
            (int) ($payload['durationMinutes'] ?? 0),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $lesson = $handler($command, $teacher);
        } catch (LessonNotFoundException) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        } catch (LessonOverlapException) {
            return ApiJson::error(self::OVERLAP, Response::HTTP_CONFLICT);
        } catch (InvalidLessonException $e) {
            return ApiJson::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(LessonView::fromLesson($lesson));
    }

    #[Route('/{id}/complete', name: 'api_lessons_complete', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function complete(int $id, CompleteLessonHandler $handler): JsonResponse
    {
        /** @var User $teacher */
        $teacher = $this->getUser();

        try {
            $lesson = $handler($id, $teacher);
        } catch (LessonNotFoundException) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        } catch (InvalidLessonException $e) {
            return ApiJson::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(LessonView::fromLesson($lesson));
    }

    #[Route('/{id}/miss', name: 'api_lessons_miss', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function miss(int $id, MissLessonHandler $handler): JsonResponse
    {
        /** @var User $teacher */
        $teacher = $this->getUser();

        try {
            $lesson = $handler($id, $teacher);
        } catch (LessonNotFoundException) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        } catch (InvalidLessonException $e) {
            return ApiJson::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(LessonView::fromLesson($lesson));
    }

    #[Route('/{id}/cancel', name: 'api_lessons_cancel', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request, CancelLessonHandler $handler): JsonResponse
    {
        /** @var User $teacher */
        $teacher = $this->getUser();

        $payload = json_decode($request->getContent(), true);
        $reason = \is_array($payload) ? (string) ($payload['reason'] ?? '') : '';
        $cancelledByClient = !\is_array($payload) || ($payload['by'] ?? 'client') !== 'teacher';

        try {
            $lesson = $handler($id, $reason, $teacher, $cancelledByClient);
        } catch (LessonNotFoundException) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        } catch (InvalidLessonException) {
            return ApiJson::error(
                'Укажите причину отмены.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['reason' => 'Укажите причину отмены.'],
            );
        }

        return $this->json(LessonView::fromLesson($lesson));
    }
}
