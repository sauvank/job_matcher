<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give every account a private candidate profile and scope job sources to that profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account ADD candidate_profile_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                account_row RECORD;
                new_profile_id INT;
            BEGIN
                FOR account_row IN SELECT id FROM app_account LOOP
                    INSERT INTO candidate_profile (preferred_contract_types, preferred_remote_policy, created_at, updated_at)
                    VALUES ('[]', 'UNKNOWN', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    RETURNING id INTO new_profile_id;

                    UPDATE app_account SET candidate_profile_id = new_profile_id WHERE id = account_row.id;
                END LOOP;
            END $$
            SQL);
        $this->addSql('ALTER TABLE app_account ALTER candidate_profile_id SET NOT NULL');
        $this->addSql('ALTER TABLE app_account ADD CONSTRAINT FK_ACCOUNT_PROFILE FOREIGN KEY (candidate_profile_id) REFERENCES candidate_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ACCOUNT_PROFILE ON app_account (candidate_profile_id)');

        $this->addSql('ALTER TABLE job_source ADD candidate_profile_id INT DEFAULT NULL');
        $this->addSql('UPDATE job_source SET candidate_profile_id = (SELECT id FROM candidate_profile ORDER BY id ASC LIMIT 1)');
        $this->addSql('ALTER TABLE job_source ALTER candidate_profile_id SET NOT NULL');
        $this->addSql('ALTER TABLE job_source ADD CONSTRAINT FK_JOB_SOURCE_PROFILE FOREIGN KEY (candidate_profile_id) REFERENCES candidate_profile (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX uniq_job_source_url');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_JOB_SOURCE_PROFILE_URL ON job_source (candidate_profile_id, url)');
        $this->addSql('CREATE INDEX IDX_JOB_SOURCE_PROFILE ON job_source (candidate_profile_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_JOB_SOURCE_PROFILE_URL');
        $this->addSql('DROP INDEX IDX_JOB_SOURCE_PROFILE');
        $this->addSql('ALTER TABLE job_source DROP CONSTRAINT FK_JOB_SOURCE_PROFILE');
        $this->addSql('ALTER TABLE job_source DROP candidate_profile_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_job_source_url ON job_source (url)');

        $this->addSql('DROP INDEX UNIQ_ACCOUNT_PROFILE');
        $this->addSql('ALTER TABLE app_account DROP CONSTRAINT FK_ACCOUNT_PROFILE');
        $this->addSql('ALTER TABLE app_account DROP candidate_profile_id');
    }
}
