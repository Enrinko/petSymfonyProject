<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use Doctrine\DBAL\Connection;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Text exposition format для Prometheus. Счётчики и гистограммы копятся
 * в Redis (см. HttpMetricsListener, MetricsAuditLogger), а gauge —
 * снимки состояния — вычисляются прямо при скрейпе: хранить их незачем.
 * Доступ ограничен в security.yaml (docker-сеть либо админ).
 */
final readonly class MetricsController
{
    public function __construct(
        private CollectorRegistry $registry,
        private UserRepositoryInterface $users,
        private ClientRepositoryInterface $clients,
        private Connection $connection,
        private LoggerInterface $logger,
        #[Autowire('%app.backup_last_success_file%')]
        private string $backupStampFile,
    ) {
    }

    #[Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->collectGauges();

        try {
            $text = new RenderTextFormat()->render($this->registry->getMetricFamilySamples());
        } catch (\Throwable $e) {
            $this->logger->error('Metrics render failed.', ['exception' => $e]);

            return new Response("# metrics unavailable\n", Response::HTTP_SERVICE_UNAVAILABLE, ['Content-Type' => 'text/plain']);
        }

        return new Response($text, Response::HTTP_OK, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }

    /**
     * Каждый gauge — в своём try/catch: сломанный источник (нет таблицы
     * messenger, недоступна БД) не прячет остальные метрики.
     */
    private function collectGauges(): void
    {
        try {
            $this->registry
                ->getOrRegisterGauge('app', 'users_total', 'Total registered users.', [])
                ->set((float) $this->users->countBySearch(''));
        } catch (\Throwable $e) {
            $this->logger->error('users_total gauge failed.', ['exception' => $e]);
        }

        try {
            $this->registry
                ->getOrRegisterGauge('app', 'clients_total', 'Total CRM clients incl. archived.', [])
                ->set((float) $this->clients->countBySearch('', true));
        } catch (\Throwable $e) {
            $this->logger->error('clients_total gauge failed.', ['exception' => $e]);
        }

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT queue_name, COUNT(*) AS cnt FROM messenger_messages WHERE delivered_at IS NULL GROUP BY queue_name',
            );

            $sizes = ['async' => 0, 'failed' => 0];

            // Внимание: у async-транспорта фактический queue_name в БД — 'default'
            // (см. messenger.yaml), поэтому здесь всё, что не 'failed', считается
            // 'async'. При добавлении новых doctrine-транспортов маппинг нужно пересмотреть.
            foreach ($rows as $row) {
                $transport = $row['queue_name'] === 'failed' ? 'failed' : 'async';
                $sizes[$transport] += (int) $row['cnt'];
            }

            $gauge = $this->registry->getOrRegisterGauge('app', 'messenger_queue_size', 'Pending messages by transport.', ['transport']);

            foreach ($sizes as $transport => $size) {
                $gauge->set((float) $size, [$transport]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('messenger_queue_size gauge failed.', ['exception' => $e]);
        }

        // Прод: volume бэкапов смонтирован в /backups — экспортируем возраст
        // метки last_success как задел под будущий алерт в Grafana (сам алерт
        // пока не настроен; свежесть бэкапа сейчас стережёт healthcheck сервиса
        // backup в compose.prod.yaml). В dev файла нет — метрика отсутствует, это норма.
        $stamp = @filemtime($this->backupStampFile);

        if ($stamp !== false) {
            try {
                $this->registry
                    ->getOrRegisterGauge('app', 'backup_last_success_timestamp_seconds', 'Unix mtime of the last successful DB backup.', [])
                    ->set((float) $stamp);
            } catch (\Throwable $e) {
                $this->logger->error('backup gauge failed.', ['exception' => $e]);
            }
        }
    }
}
