<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Service\EsnDetector;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Twig\JobOfferExtension;
use PHPUnit\Framework\TestCase;

final class JobOfferExtensionTest extends TestCase
{
    public function testItExposesFunctionsAndFilters(): void
    {
        $extension = new JobOfferExtension(new EsnDetector());

        self::assertCount(1, $extension->getFunctions());
        self::assertCount(1, $extension->getFilters());

        $source = new JobSource(new CandidateProfile(), 'Source', 'https://example.test/jobs', JobProviderType::FAKE);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: '125',
            url: 'https://example.test/jobs/125',
            title: 'Consultant',
            company: 'Alten',
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

        self::assertTrue($extension->isEsn($offer));
    }
}
