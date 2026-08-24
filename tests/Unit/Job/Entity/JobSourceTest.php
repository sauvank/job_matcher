<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Enum\JobSourceSyncStatus;
use PHPUnit\Framework\TestCase;

final class JobSourceTest extends TestCase
{
    public function testItExtractsTheSearchLabelFromTheGeneratedSourceName(): void
    {
        $source = new JobSource(
            new CandidateProfile(),
            'Indeed — Développeur PHP — Symfony — Lyon',
            'https://example.test/jobs',
            JobProviderType::INDEED,
        );

        self::assertSame('Développeur PHP — Symfony', $source->getSearchLabel());
    }

    public function testItUsesTheWholeNameForANonGeneratedSource(): void
    {
        $source = new JobSource(new CandidateProfile(), 'Recherche personnalisée', 'https://example.test/jobs', JobProviderType::FAKE);

        self::assertSame('Recherche personnalisée', $source->getSearchLabel());
    }

    public function testItUpdatesItsGeneratedSearch(): void
    {
        $source = new JobSource(new CandidateProfile(), 'Ancienne recherche', 'https://example.test/old', JobProviderType::HELLOWORK);
        $source->markSyncStarted();
        $source->completeSync();

        $source->updateSearch('HelloWork — Développeur PHP — Paris', 'https://example.test/new');

        self::assertSame('HelloWork — Développeur PHP — Paris', $source->getName());
        self::assertSame('https://example.test/new', $source->getUrl());
        self::assertNull($source->getLastSyncStartedAt());
        self::assertNull($source->getLastSuccessAt());
        self::assertNull($source->getLastError());
        self::assertSame(JobSourceSyncStatus::IDLE, $source->getSyncStatus());
        self::assertSame(0, $source->getProcessedOfferCount());
    }

    public function testItTracksImportProgress(): void
    {
        $source = new JobSource(new CandidateProfile(), 'Recherche', 'https://example.test/jobs', JobProviderType::HELLOWORK);

        $source->queueSync();
        self::assertSame(JobSourceSyncStatus::QUEUED, $source->getSyncStatus());
        self::assertTrue($source->isSyncPending());

        $source->markSyncStarted();
        $source->recordProcessedOffer();
        $source->recordProcessedOffer();
        self::assertSame(JobSourceSyncStatus::RUNNING, $source->getSyncStatus());
        self::assertSame(2, $source->getProcessedOfferCount());

        $source->completeSync();
        self::assertSame(JobSourceSyncStatus::SUCCEEDED, $source->getSyncStatus());
        self::assertFalse($source->isSyncPending());
    }
}
