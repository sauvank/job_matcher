<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821182352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align PostgreSQL metadata and index names with the Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN candidate_profile.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN candidate_profile.updated_at IS \'\'');
        $this->addSql('ALTER INDEX idx_candidate_skill_profile RENAME TO IDX_66DD0F8BFE3D0586');
        $this->addSql('ALTER INDEX idx_candidate_skill_skill RENAME TO IDX_66DD0F8B5585C142');
        $this->addSql('COMMENT ON COLUMN cv_document.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN cv_document.updated_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN cv_document.analyzed_at IS \'\'');
        $this->addSql('ALTER INDEX uniq_cv_document_stored_filename RENAME TO UNIQ_49466FB2DF8EB9B7');
        $this->addSql('ALTER INDEX idx_cv_document_profile RENAME TO IDX_49466FB2FE3D0586');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN candidate_profile.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN candidate_profile.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_66dd0f8b5585c142 RENAME TO idx_candidate_skill_skill');
        $this->addSql('ALTER INDEX idx_66dd0f8bfe3d0586 RENAME TO idx_candidate_skill_profile');
        $this->addSql('COMMENT ON COLUMN cv_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cv_document.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cv_document.analyzed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX uniq_49466fb2df8eb9b7 RENAME TO uniq_cv_document_stored_filename');
        $this->addSql('ALTER INDEX idx_49466fb2fe3d0586 RENAME TO idx_cv_document_profile');
    }
}
