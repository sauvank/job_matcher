<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align authentication index names and metadata with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN app_account.created_at IS ''");
        $this->addSql('ALTER INDEX uniq_account_email RENAME TO UNIQ_906BD6E9E7927C74');
        $this->addSql('ALTER INDEX uniq_account_google_subject RENAME TO UNIQ_906BD6E958A03FC');
        $this->addSql('ALTER INDEX uniq_account_profile RENAME TO UNIQ_906BD6E9FE3D0586');
        $this->addSql('ALTER INDEX idx_job_source_profile RENAME TO IDX_F8E52F2FE3D0586');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN app_account.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER INDEX UNIQ_906BD6E9E7927C74 RENAME TO uniq_account_email');
        $this->addSql('ALTER INDEX UNIQ_906BD6E958A03FC RENAME TO uniq_account_google_subject');
        $this->addSql('ALTER INDEX UNIQ_906BD6E9FE3D0586 RENAME TO uniq_account_profile');
        $this->addSql('ALTER INDEX IDX_F8E52F2FE3D0586 RENAME TO idx_job_source_profile');
    }
}
