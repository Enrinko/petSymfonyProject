<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'password_reset_token: уникальный индекс по token_hash вместо обычного';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_password_reset_token_hash');
        $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_token_hash ON password_reset_token (token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_password_reset_token_hash');
        $this->addSql('CREATE INDEX idx_password_reset_token_hash ON password_reset_token (token_hash)');
    }
}
