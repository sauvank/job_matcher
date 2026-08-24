<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy Welcome to the Jungle sources and offers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM job_offer WHERE source_id IN (SELECT id FROM job_source WHERE provider = 'WELCOME_TO_THE_JUNGLE')");
        $this->addSql("DELETE FROM job_source WHERE provider = 'WELCOME_TO_THE_JUNGLE'");
    }

    public function down(Schema $schema): void
    {
    }
}
