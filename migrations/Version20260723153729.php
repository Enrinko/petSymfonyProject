<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723153729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User profile: display_name and avatar_path columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD display_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD avatar_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP display_name');
        $this->addSql('ALTER TABLE "user" DROP avatar_path');
    }
}
