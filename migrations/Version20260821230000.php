<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the status and structured result of semantic job compatibility analyses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_match ADD semantic_analysis JSON DEFAULT NULL, ADD semantic_analysis_status VARCHAR(255) DEFAULT 'NOT_REQUESTED' NOT NULL, ADD semantic_analyzer VARCHAR(120) DEFAULT NULL, ADD semantic_error TEXT DEFAULT NULL, ADD semantic_analyzed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match DROP semantic_analysis, DROP semantic_analysis_status, DROP semantic_analyzer, DROP semantic_error, DROP semantic_analyzed_at');
    }
}
