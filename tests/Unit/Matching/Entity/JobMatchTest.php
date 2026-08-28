<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use PHPUnit\Framework\TestCase;

final class JobMatchTest extends TestCase
{
    public function testInitialStatusIsUnprocessedWithoutReason(): void
    {
        $match = $this->createMatch();

        self::assertSame(JobApplicationStatus::UNPROCESSED, $match->getApplicationStatus());
        self::assertNull($match->getStatusReason());
        self::assertNull($match->getStatusUpdatedAt());
    }

    public function testUpdateApplicationStatusWithReason(): void
    {
        $match = $this->createMatch();
        $before = new \DateTimeImmutable();

        $match->updateApplicationStatus(JobApplicationStatus::INTERESTED, 'Projet attractif et stack moderne');

        self::assertSame(JobApplicationStatus::INTERESTED, $match->getApplicationStatus());
        self::assertSame('Projet attractif et stack moderne', $match->getStatusReason());
        self::assertNotNull($match->getStatusUpdatedAt());
        self::assertGreaterThanOrEqual($before, $match->getStatusUpdatedAt());
    }

    public function testUpdateApplicationStatusTrimsWhitespaceReasonToNull(): void
    {
        $match = $this->createMatch();
        $match->updateApplicationStatus(JobApplicationStatus::NOT_INTERESTED, "   \n\t  ");

        self::assertSame(JobApplicationStatus::NOT_INTERESTED, $match->getApplicationStatus());
        self::assertNull($match->getStatusReason());
        self::assertNotNull($match->getStatusUpdatedAt());
    }

    public function testUpdateApplicationStatusWithNullReason(): void
    {
        $match = $this->createMatch();
        $match->updateApplicationStatus(JobApplicationStatus::APPLIED, null);

        self::assertSame(JobApplicationStatus::APPLIED, $match->getApplicationStatus());
        self::assertNull($match->getStatusReason());
        self::assertNotNull($match->getStatusUpdatedAt());
    }

    private function createMatch(): JobMatch
    {
        $profile = new CandidateProfile();
        $source = new JobSource($profile, 'Source Test', 'https://example.test/source', JobProviderType::FAKE);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-123',
            url: 'https://example.test/offer/123',
            title: 'Lead Dev PHP',
            company: 'ACME',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: 55000,
            maximumSalary: 65000,
            remotePolicy: 'PARTIAL',
            yearsOfExperience: 5,
            description: 'Super offre',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));

        return new JobMatch($profile, $offer, new MatchScore(80, 80, 80, 80, 80, 80, 80, 80, 80, [], [], [], []));
    }
}
