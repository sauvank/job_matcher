<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferStatus;
use App\Job\Enum\JobProviderType;
use PHPUnit\Framework\TestCase;

final class JobOfferTest extends TestCase
{
    public function testItExpiresAfterItsValidityDateAndReactivatesWhenSeenAgain(): void
    {
        $normalizedOffer = $this->offer(new \DateTimeImmutable('2026-08-22'));
        $offer = new JobOffer(
            new JobSource(new CandidateProfile(), 'Source', 'https://example.test/jobs', JobProviderType::FAKE),
            $normalizedOffer,
        );

        self::assertTrue($offer->hasExpiredBy(new \DateTimeImmutable('2026-08-23 12:00:00')));

        $offer->markExpired();
        self::assertSame(JobOfferStatus::EXPIRED, $offer->getStatus());

        $offer->updateFrom($normalizedOffer);
        self::assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
    }

    public function testItExposesFirstSeenAtAndDetectsEsnStatus(): void
    {
        $now = new \DateTimeImmutable();
        $source = new JobSource(new CandidateProfile(), 'Source', 'https://example.test/jobs', JobProviderType::FAKE);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: '124',
            url: 'https://example.test/jobs/124',
            title: 'Lead Tech',
            company: 'Capgemini',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: null,
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));

        self::assertGreaterThanOrEqual($now->getTimestamp(), $offer->getFirstSeenAt()->getTimestamp());
        self::assertTrue($offer->isEsn());
    }

    private function offer(\DateTimeImmutable $validThrough): NormalizedJobOffer
    {
        return new NormalizedJobOffer(
            externalId: '123',
            url: 'https://example.test/jobs/123',
            title: 'Développeur PHP',
            company: null,
            location: null,
            contractType: null,
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: null,
            publishedAt: null,
            validThrough: $validThrough,
            rawPayload: [],
        );
    }
}
