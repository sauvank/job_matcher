<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow several job searches while preventing duplicate source URLs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_job_source_url ON job_source (url)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_job_source_url');
    }
}
