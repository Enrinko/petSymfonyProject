<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * pg_trgm + GIN-индексы под ILIKE-поиск палитры (Ctrl+K),
 * чтобы подстрочный поиск не деградировал с ростом базы.
 */
final class Version20260722182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'pg_trgm GIN-индексы по client.name и note.content для глобального поиска';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE INDEX idx_client_name_trgm ON client USING gin (LOWER(name) gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_note_content_trgm ON note USING gin (LOWER(content) gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_note_content_trgm');
        $this->addSql('DROP INDEX idx_client_name_trgm');
    }
}
