<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add applied_contract_types column on cv_document.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_document ADD applied_contract_types JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_document DROP applied_contract_types');
    }
}
