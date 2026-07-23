<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Единый конверт ошибок API: {"message": string, "errors": object|null}.
 * Успешные ответы конвертом не оборачиваются — только ошибки.
 */
final class ApiJson
{
    private function __construct()
    {
    }

    /**
     * @param array<string, string>|null $errors
     */
    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => $errors], $status);
    }

    public static function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        return self::error(
            'Данные не прошли валидацию.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ViolationsFormatter::toFieldErrors($violations),
        );
    }

    public static function invalidJson(): JsonResponse
    {
        return self::error('Тело запроса должно быть корректным JSON.', Response::HTTP_BAD_REQUEST);
    }
}
