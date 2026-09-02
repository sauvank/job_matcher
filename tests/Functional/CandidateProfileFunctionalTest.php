<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Enum\RemotePolicy;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use Doctrine\ORM\EntityManagerInterface;

final class CandidateProfileFunctionalTest extends AuthenticatedWebTestCase
{
    public function testProfileCanBeUpdatedWithRemoteAndExclusions(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $crawler = $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('💾 Enregistrer les modifications')->form([
            'candidate_profile_details[title]' => 'Senior Backend Engineer',
            'candidate_profile_details[location]' => 'Lyon',
            'candidate_profile_details[preferredRemotePolicy]' => RemotePolicy::REMOTE->value,
            'candidate_profile_details[yearsOfExperience]' => '6',
            'candidate_profile_details[minimumSalary]' => '55000',
            'candidate_profile_details[minimumDailyRate]' => '600',
            'candidate_profile_details[excludedCompaniesText]' => 'BadCorp, EvilESN',
            'candidate_profile_details[excludedKeywordsText]' => 'WordPress, Legacy',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/profile');

        $profileId = $account->getCandidateProfile()->getId();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $updatedProfile = $em->find(\App\Candidate\Entity\CandidateProfile::class, $profileId);
        self::assertNotNull($updatedProfile);

        self::assertSame('Senior Backend Engineer', $updatedProfile->getTitle());
        self::assertSame(RemotePolicy::REMOTE, $updatedProfile->getPreferredRemotePolicy());
        self::assertSame(['BadCorp', 'EvilESN'], $updatedProfile->getExcludedCompanies());
        self::assertSame(['WordPress', 'Legacy'], $updatedProfile->getExcludedKeywords());
    }

    public function testJobMatchingFiltersOutExcludedCompaniesAndKeywords(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $profile = $account->getCandidateProfile();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $profile->updateDetails(
            'Dev Symfony',
            'Lyon',
            5,
            ['CDI'],
            45000,
            450,
            RemotePolicy::REMOTE,
            ['BadCorp'],
            ['WordPress'],
        );
        $em->flush();

        $source = new JobSource($profile, 'Apec Search', 'https://example.test', JobProviderType::APEC);
        $em->persist($source);

        // Offer 1: Valid
        $offerGood = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-good',
            url: 'https://example.test/good',
            title: 'Développeur Symfony Full Remote',
            company: 'GoodCorp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 50000,
            maximumSalary: 55000,
            remotePolicy: 'REMOTE',
            yearsOfExperience: 4,
            description: 'Super projet Symfony moderne.',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $em->persist($offerGood);
        $matchGood = new JobMatch($profile, $offerGood, new MatchScore(90, 90, 90, 90, 90, 90, 90, 90, 90, [], [], [], []));
        $em->persist($matchGood);

        // Offer 2: Excluded Company
        $offerBadCo = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-bad-co',
            url: 'https://example.test/bad-co',
            title: 'Lead Symfony Full Remote',
            company: 'BadCorp SAS',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 50000,
            maximumSalary: 55000,
            remotePolicy: 'REMOTE',
            yearsOfExperience: 4,
            description: 'Projet sympa.',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $em->persist($offerBadCo);
        $matchBadCo = new JobMatch($profile, $offerBadCo, new MatchScore(95, 95, 95, 95, 95, 95, 95, 95, 95, [], [], [], []));
        $em->persist($matchBadCo);

        // Offer 3: Excluded Keyword in description
        $offerBadKw = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-bad-kw',
            url: 'https://example.test/bad-kw',
            title: 'Développeur Full Remote',
            company: 'CoolTech',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 50000,
            maximumSalary: 55000,
            remotePolicy: 'REMOTE',
            yearsOfExperience: 4,
            description: 'Maintenance de sites WordPress et Symfony.',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $em->persist($offerBadKw);
        $matchBadKw = new JobMatch($profile, $offerBadKw, new MatchScore(85, 85, 85, 85, 85, 85, 85, 85, 85, [], [], [], []));
        $em->persist($matchBadKw);

        $offerOnSite = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-on-site',
            url: 'https://example.test/on-site',
            title: 'Développeur Symfony sur site',
            company: 'OfficeCorp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 50000,
            maximumSalary: 55000,
            remotePolicy: 'ON_SITE',
            yearsOfExperience: 4,
            description: null,
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $em->persist($offerOnSite);
        $matchOnSite = new JobMatch($profile, $offerOnSite, new MatchScore(80, 80, 80, 80, 80, 80, 80, 80, 80, [], [], [], []));
        $em->persist($matchOnSite);

        $em->flush();

        $client->request('GET', '/jobs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Développeur Symfony Full Remote');
        self::assertSelectorTextNotContains('body', 'Lead Symfony Full Remote');
        self::assertSelectorTextNotContains('body', 'CoolTech');
        self::assertSelectorTextNotContains('body', 'OfficeCorp');
    }
}
