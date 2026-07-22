<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ViolationsFormatter
{
    private function __construct()
    {
    }

    /**
     * Преобразует список нарушений в формат ответа API: { "field": "message" }.
     *
     * @return array<string, string>
     */
    public static function toFieldErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = preg_replace('/\[\d+]$/', '', $violation->getPropertyPath()) ?? $violation->getPropertyPath();

            if (!isset($errors[$field])) {
                $errors[$field] = (string) $violation->getMessage();
            }
        }

        return $errors;
    }
}
