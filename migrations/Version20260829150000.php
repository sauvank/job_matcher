<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add B-tree indexes on frequently filtered and sorted columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_job_offer_status ON job_offer (status)');
        $this->addSql('CREATE INDEX idx_job_offer_first_seen_at ON job_offer (first_seen_at)');
        $this->addSql('CREATE INDEX idx_job_match_application_status ON job_match (application_status)');
        $this->addSql('CREATE INDEX idx_job_match_profile_semantic ON job_match (candidate_profile_id, semantic_score)');
        $this->addSql('CREATE INDEX idx_job_source_enabled ON job_source (enabled)');
        $this->addSql('CREATE INDEX idx_account_alert_email_enabled ON app_account (alert_email_enabled)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_job_offer_status');
        $this->addSql('DROP INDEX idx_job_offer_first_seen_at');
        $this->addSql('DROP INDEX idx_job_match_application_status');
        $this->addSql('DROP INDEX idx_job_match_profile_semantic');
        $this->addSql('DROP INDEX idx_job_source_enabled');
        $this->addSql('DROP INDEX idx_account_alert_email_enabled');
    }
}
