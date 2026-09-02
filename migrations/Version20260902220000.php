<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add excluded_companies and excluded_keywords columns on candidate_profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profile ADD excluded_companies JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE candidate_profile ADD excluded_keywords JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_profile DROP excluded_companies');
        $this->addSql('ALTER TABLE candidate_profile DROP excluded_keywords');
    }
}
