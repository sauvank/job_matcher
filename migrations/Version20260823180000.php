<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate candidate skills and job searches with the active CV.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_document ADD applied_title VARCHAR(160) DEFAULT NULL, ADD applied_location VARCHAR(160) DEFAULT NULL, ADD applied_years_of_experience INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidate_profile ADD active_cv_document_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidate_profile ADD CONSTRAINT FK_CANDIDATE_PROFILE_ACTIVE_CV FOREIGN KEY (active_cv_document_id) REFERENCES cv_document (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E8607AEE547E4CA ON candidate_profile (active_cv_document_id)');

        $this->addSql(<<<'SQL'
            WITH latest_applied_cv AS (
                SELECT DISTINCT ON (candidate_profile_id) id, candidate_profile_id
                FROM cv_document
                WHERE status = 'APPLIED'
                ORDER BY candidate_profile_id, updated_at DESC, id DESC
            )
            UPDATE candidate_profile profile
            SET active_cv_document_id = latest.id
            FROM latest_applied_cv latest
            WHERE profile.id = latest.candidate_profile_id
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE cv_document document
            SET applied_title = profile.title,
                applied_location = profile.location,
                applied_years_of_experience = profile.years_of_experience
            FROM candidate_profile profile
            WHERE profile.active_cv_document_id = document.id
            SQL);

        $this->addSql('ALTER TABLE candidate_skill ADD cv_document_id INT DEFAULT NULL');
        $this->addSql('UPDATE candidate_skill skill SET cv_document_id = profile.active_cv_document_id FROM candidate_profile profile WHERE skill.candidate_profile_id = profile.id');
        $this->addSql('ALTER TABLE candidate_skill ADD CONSTRAINT FK_CANDIDATE_SKILL_CV FOREIGN KEY (cv_document_id) REFERENCES cv_document (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_66DD0F8BDD417CCA ON candidate_skill (cv_document_id)');
        $this->addSql('DROP INDEX uniq_candidate_skill');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidate_skill_cv ON candidate_skill (cv_document_id, skill_id)');

        $this->addSql('ALTER TABLE job_source ADD cv_document_id INT DEFAULT NULL');
        $this->addSql('UPDATE job_source source SET cv_document_id = profile.active_cv_document_id FROM candidate_profile profile WHERE source.candidate_profile_id = profile.id');
        $this->addSql('ALTER TABLE job_source ADD CONSTRAINT FK_JOB_SOURCE_CV FOREIGN KEY (cv_document_id) REFERENCES cv_document (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_F8E52F2DD417CCA ON job_source (cv_document_id)');
        $this->addSql('DROP INDEX uniq_job_source_profile_url');
        $this->addSql('CREATE UNIQUE INDEX uniq_job_source_cv_url ON job_source (cv_document_id, url)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM candidate_skill duplicate USING candidate_skill kept WHERE duplicate.candidate_profile_id = kept.candidate_profile_id AND duplicate.skill_id = kept.skill_id AND duplicate.id > kept.id');
        $this->addSql('DELETE FROM job_source duplicate USING job_source kept WHERE duplicate.candidate_profile_id = kept.candidate_profile_id AND duplicate.url = kept.url AND duplicate.id > kept.id');

        $this->addSql('DROP INDEX uniq_job_source_cv_url');
        $this->addSql('DROP INDEX IDX_F8E52F2DD417CCA');
        $this->addSql('ALTER TABLE job_source DROP CONSTRAINT FK_JOB_SOURCE_CV');
        $this->addSql('ALTER TABLE job_source DROP cv_document_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_job_source_profile_url ON job_source (candidate_profile_id, url)');

        $this->addSql('DROP INDEX uniq_candidate_skill_cv');
        $this->addSql('DROP INDEX IDX_66DD0F8BDD417CCA');
        $this->addSql('ALTER TABLE candidate_skill DROP CONSTRAINT FK_CANDIDATE_SKILL_CV');
        $this->addSql('ALTER TABLE candidate_skill DROP cv_document_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidate_skill ON candidate_skill (candidate_profile_id, skill_id)');

        $this->addSql('DROP INDEX IDX_E8607AEE547E4CA');
        $this->addSql('ALTER TABLE candidate_profile DROP CONSTRAINT FK_CANDIDATE_PROFILE_ACTIVE_CV');
        $this->addSql('ALTER TABLE candidate_profile DROP active_cv_document_id');
        $this->addSql('ALTER TABLE cv_document DROP applied_title, DROP applied_location, DROP applied_years_of_experience');
    }
}
