<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Client\Import\DuplicatePolicy;
use App\Application\Client\Import\ImportClientsHandler;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Tag\Tag;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Csv\ClientCsvFormatException;
use App\Infrastructure\Csv\ClientCsvRowsParser;
use App\Infrastructure\Csv\CsvInjectionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
#[IsGranted('ROLE_USER')]
final class ClientImportExportController extends AbstractController
{
    private const int MAX_FILE_BYTES = 5 * 1024 * 1024;

    /**
     * Экспорт текущей выборки (те же фильтры, что у списка) в CSV.
     * BOM — чтобы Excel не ломал кириллицу; стриминг — чтобы не собирать файл в памяти.
     */
    #[Route('/export', name: 'api_clients_export', methods: ['GET'])]
    public function export(Request $request, ClientRepositoryInterface $clients): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $owner = $this->isGranted(Role::Moderator->value) ? null : $user;

        $search = trim((string) $request->query->get('search', ''));
        $includeArchived = $request->query->getBoolean('archived');
        $tagsParam = trim((string) $request->query->get('tags', ''));
        $tags = $tagsParam === '' ? [] : array_values(array_filter(array_map(
            static fn (string $tag): string => mb_strtolower(trim($tag)),
            explode(',', $tagsParam),
        ), static fn (string $tag): bool => $tag !== ''));

        $response = new StreamedResponse(function () use ($clients, $search, $includeArchived, $owner, $tags): void {
            $out = fopen('php://output', 'w');
            \assert($out !== false);

            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['name', 'email', 'phone', 'comment', 'tags', 'created_at', 'archived_at'], ',', '"', '');

            foreach ($clients->iterateBySearch($search, $includeArchived, $owner, $tags) as $client) {
                // Пользовательские поля — через guard (CSV formula injection);
                // системные даты начинаются с цифры и в защите не нуждаются.
                fputcsv($out, [
                    CsvInjectionGuard::escape($client->getName()),
                    CsvInjectionGuard::escape($client->getEmail()),
                    CsvInjectionGuard::escape($client->getPhone()),
                    CsvInjectionGuard::escape($client->getComment()),
                    CsvInjectionGuard::escape(
                        implode('; ', array_map(static fn (Tag $tag): string => $tag->getName(), $client->getTags())),
                    ),
                    $client->getCreatedAt()->format('Y-m-d H:i'),
                    $client->getArchivedAt()?->format('Y-m-d H:i') ?? '',
                ], ',', '"', '');
            }

            fclose($out);
        });

        $date = new \DateTimeImmutable()->format('Y-m-d');
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            sprintf('ученики-%s.csv', $date),
            sprintf('clients-%s.csv', $date),
        ));

        return $response;
    }

    #[Route('/import', name: 'api_clients_import', methods: ['POST'])]
    public function import(
        Request $request,
        ClientCsvRowsParser $parser,
        ImportClientsHandler $handler,
    ): JsonResponse {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return ApiJson::error('Файл не получен.', Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            return ApiJson::error('Файл больше 5 МБ.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $policy = DuplicatePolicy::tryFrom((string) $request->request->get('duplicates', 'skip'))
            ?? DuplicatePolicy::Skip;

        try {
            $rows = $parser->parse($file->getContent());
        } catch (ClientCsvFormatException $exception) {
            return ApiJson::error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $owner */
        $owner = $this->getUser();

        return $this->json($handler($rows, $policy, $owner));
    }
}
