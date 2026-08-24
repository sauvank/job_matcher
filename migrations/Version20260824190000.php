<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore Welcome to the Jungle sources with the structured search integration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO job_source (
                candidate_profile_id,
                cv_document_id,
                name,
                url,
                provider,
                enabled,
                last_sync_started_at,
                last_success_at,
                last_error,
                sync_status,
                processed_offer_count,
                created_at,
                updated_at
            )
            SELECT
                source.candidate_profile_id,
                source.cv_document_id,
                LEFT(REGEXP_REPLACE(source.name, '^[^—]+ — ', 'Welcome to the Jungle — '), 120),
                'https://www.welcometothejungle.com/fr/jobs?query='
                    || SUBSTRING(source.url FROM '[?&]k=([^&]+)')
                    || '&aroundQuery='
                    || SUBSTRING(source.url FROM '[?&]l=([^&]+)'),
                'WELCOME_TO_THE_JUNGLE',
                TRUE,
                NULL,
                NULL,
                NULL,
                'IDLE',
                0,
                NOW(),
                NOW()
            FROM job_source source
            WHERE source.provider = 'HELLOWORK'
                AND SUBSTRING(source.url FROM '[?&]k=([^&]+)') IS NOT NULL
                AND SUBSTRING(source.url FROM '[?&]l=([^&]+)') IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM job_source existing
                    WHERE existing.candidate_profile_id = source.candidate_profile_id
                        AND existing.cv_document_id IS NOT DISTINCT FROM source.cv_document_id
                        AND existing.url = 'https://www.welcometothejungle.com/fr/jobs?query='
                            || SUBSTRING(source.url FROM '[?&]k=([^&]+)')
                            || '&aroundQuery='
                            || SUBSTRING(source.url FROM '[?&]l=([^&]+)')
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM job_source WHERE provider = 'WELCOME_TO_THE_JUNGLE'");
    }
}
