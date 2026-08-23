<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;

final class FakeJobProvider implements JobProviderInterface
{
    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::FAKE;
    }

    public function fetch(JobSource $source): iterable
    {
        yield new NormalizedJobOffer(
            externalId: 'fake-php-symfony',
            url: 'https://example.test/jobs/fake-php-symfony',
            title: 'Développeur backend PHP Symfony',
            company: 'Entreprise de démonstration',
            location: 'Lyon 69000',
            contractType: 'CDI',
            minimumSalary: 45000,
            maximumSalary: 52000,
            remotePolicy: 'REMOTE_AVAILABLE',
            yearsOfExperience: 5,
            description: 'Développement d’API avec PHP, Symfony, PostgreSQL et Docker.',
            publishedAt: new \DateTimeImmutable('2026-08-20'),
            validThrough: null,
            rawPayload: ['provider' => 'fake'],
        );
    }

    public function checkAvailability(JobOffer $offer): JobOfferAvailability
    {
        return JobOfferAvailability::AVAILABLE;
    }
}
