<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\User\User;

/**
 * Сводка «Пульта»: агрегаты по видимым учеником и последние заметки.
 * Модули занятий/посещаемости появятся отдельными секциями по мере готовности.
 */
final readonly class DashboardHandler
{
    private const int RECENT_NOTES = 5;

    public function __construct(
        private ClientRepositoryInterface $clients,
        private NoteRepositoryInterface $notes,
    ) {
    }

    public function __invoke(?User $owner): DashboardView
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        return new DashboardView(
            $this->clients->countBySearch('', false, $owner),
            $this->clients->countCreatedSince($monthStart, $owner),
            array_map(
                static fn (Note $note): RecentNoteView => RecentNoteView::fromNote($note),
                $this->notes->findRecentForOwner($owner, self::RECENT_NOTES),
            ),
        );
    }
}
