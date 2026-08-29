<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829144000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alert_sent_at timestamp column to job_match table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match ADD alert_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_job_match_alert_sent_at ON job_match (alert_sent_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_job_match_alert_sent_at');
        $this->addSql('ALTER TABLE job_match DROP alert_sent_at');
    }
}
