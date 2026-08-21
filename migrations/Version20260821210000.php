<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the status and processed offer count of job source imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_source ADD sync_status VARCHAR(255) DEFAULT 'IDLE' NOT NULL");
        $this->addSql('ALTER TABLE job_source ADD processed_offer_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE job_source ALTER sync_status DROP DEFAULT');
        $this->addSql('ALTER TABLE job_source ALTER processed_offer_count DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_source DROP sync_status');
        $this->addSql('ALTER TABLE job_source DROP processed_offer_count');
    }
}
