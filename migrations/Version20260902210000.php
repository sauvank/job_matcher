<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add minimum_daily_rate column on candidate_profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_profile ADD minimum_daily_rate INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_profile DROP minimum_daily_rate');
    }
}
