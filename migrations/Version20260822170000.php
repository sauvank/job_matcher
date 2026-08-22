<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require verified email addresses for new local accounts while preserving existing accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE app_account SET email_verified_at = created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account DROP email_verified_at');
    }
}
