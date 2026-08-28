<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application status, reason, and status timestamp to job matches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_match ADD application_status VARCHAR(40) DEFAULT 'UNPROCESSED' NOT NULL");
        $this->addSql('ALTER TABLE job_match ADD status_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_match ADD status_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match DROP application_status');
        $this->addSql('ALTER TABLE job_match DROP status_reason');
        $this->addSql('ALTER TABLE job_match DROP status_updated_at');
    }
}
