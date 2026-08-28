<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add daily alert email settings to app_account table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account ADD alert_email_enabled BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE app_account ADD alert_score_threshold INT DEFAULT 70 NOT NULL');
        $this->addSql('ALTER TABLE app_account ADD last_alert_email_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account DROP alert_email_enabled');
        $this->addSql('ALTER TABLE app_account DROP alert_score_threshold');
        $this->addSql('ALTER TABLE app_account DROP last_alert_email_sent_at');
    }
}
