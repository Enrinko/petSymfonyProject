<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Audit\AuditFilter;

final readonly class ListAuditEventsHandler
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private AuditEventRepositoryInterface $events,
    ) {
    }

    /**
     * @return array{events: list<AuditEventView>, total: int, page: int, perPage: int}
     */
    public function __invoke(ListAuditEventsQuery $query): array
    {
        $page = max(1, $query->page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $query->perPage));

        $filter = new AuditFilter(
            $query->action,
            $query->actorEmail,
            $this->parseDate($query->from),
            // Конец дня: фильтр «по 23.07» включает весь день 23.07
            $this->parseDate($query->to)?->setTime(23, 59, 59),
        );

        return [
            'events' => array_map(
                AuditEventView::fromEvent(...),
                $this->events->findPage($filter, $page, $perPage),
            ),
            'total' => $this->events->countByFilter($filter),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date === false ? null : $date->setTime(0, 0);
    }
}
