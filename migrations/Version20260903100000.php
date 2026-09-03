<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add semantic_analysis_started_at column on job_match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match ADD semantic_analysis_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match DROP semantic_analysis_started_at');
    }
}
