<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

final readonly class DashboardView
{
    /**
     * @param list<RecentNoteView> $recentNotes
     */
    public function __construct(
        public int $clientsTotal,
        public int $clientsNewThisMonth,
        public array $recentNotes,
    ) {
    }
}
