<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723172554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin hardening: user.active flag and deactivated_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD active BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP active');
        $this->addSql('ALTER TABLE "user" DROP deactivated_at');
    }
}
