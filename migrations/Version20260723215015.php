<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723215015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email verification: user.verified_at (existing users grandfathered as verified)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Существующие пользователи уже живут в системе — считаем их подтверждёнными
        $this->addSql('UPDATE "user" SET verified_at = created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP verified_at');
    }
}
