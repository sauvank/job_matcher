<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Service\TechnicalRequirementExtractor;
use PHPUnit\Framework\TestCase;

final class TechnicalRequirementExtractorTest extends TestCase
{
    public function testItCompletesIncompleteStructuredSkillsFromTheTechnicalEnvironment(): void
    {
        $description = <<<'HTML'
            <p>Présentation de l’entreprise.</p>
            <p>Environnement technique :<br>
            Backend : PHP 8.x, Symfony (6.x / 7)<br>
            Architecture : Microservices, API Gateway, Event-driven, CQRS<br>
            API : REST, OpenAPI / Swagger<br>
            Data : PostgreSQL, Redis, Elasticsearch<br>
            Ops : Docker, Kubernetes, CI/CD (GitLab CI, GitHub Actions...)</p>
            <h2>Le profil recherché</h2>
            HTML;
        $offer = new JobOffer(
            new JobSource(new CandidateProfile(), 'HelloWork', 'https://example.test', JobProviderType::HELLOWORK),
            new NormalizedJobOffer(
                externalId: '79315367',
                url: 'https://www.hellowork.com/fr-fr/emplois/79315367.html',
                title: 'Tech Lead PHP H/F',
                company: 'Test',
                location: 'Lyon',
                contractType: 'CDI',
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: 'REMOTE_AVAILABLE',
                yearsOfExperience: null,
                description: strip_tags($description),
                publishedAt: null,
                validThrough: null,
                rawPayload: ['description' => $description, 'skills' => ['Clean', 'REST', 'PHP']],
            ),
        );

        $requirements = (new TechnicalRequirementExtractor())->extract($offer);

        self::assertNotContains('Clean', $requirements);
        self::assertEqualsCanonicalizing([
            'REST',
            'PHP',
            'Symfony',
            'Microservices',
            'API Gateway',
            'Event-driven',
            'CQRS',
            'OpenAPI',
            'Swagger',
            'PostgreSQL',
            'Redis',
            'Elasticsearch',
            'Docker',
            'Kubernetes',
            'CI/CD',
        ], $requirements);
    }

    public function testItExtractsAngularFromAStackTechnicalLine(): void
    {
        $description = '<p>Stack technique : PHP 8.4 / Symfony 6.4 / API REST / Git / MySQL / MariaDB / Angular 21<br /></p>';
        $offer = new JobOffer(
            new JobSource(new CandidateProfile(), 'HelloWork', 'https://example.test', JobProviderType::HELLOWORK),
            new NormalizedJobOffer(
                externalId: '79162910',
                url: 'https://www.hellowork.com/fr-fr/emplois/79162910.html',
                title: 'Développeur Symfony Angular H/F',
                company: 'Test',
                location: 'Lyon',
                contractType: 'CDI',
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: 'REMOTE_AVAILABLE',
                yearsOfExperience: 5,
                description: strip_tags($description),
                publishedAt: null,
                validThrough: null,
                rawPayload: ['description' => $description, 'skills' => ['Rigueur et méthode', 'PHP']],
            ),
        );

        self::assertEqualsCanonicalizing(
            ['Rigueur et méthode', 'PHP', 'Symfony', 'API REST', 'Git', 'MySQL', 'MariaDB', 'Angular'],
            (new TechnicalRequirementExtractor())->extract($offer),
        );
    }
}
