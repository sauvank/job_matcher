<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821230100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the semantic analysis status column default with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_match ALTER semantic_analysis_status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_match ALTER semantic_analysis_status SET DEFAULT 'NOT_REQUESTED'");
    }
}
