<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\DTO\ManualJobOfferData;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Entity\JobMatch;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Service\MatchJobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ManualJobOfferImporter
{
    private const SOURCE_URL = 'manual://job-offers';

    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private JobOfferRepositoryInterface $offerRepository,
        private MatchJobOfferService $matchService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function importOffer(CandidateProfile $profile, ManualJobOfferData $data): JobMatch
    {
        $source = $this->sourceRepository->findOneByProfileAndUrl($profile, self::SOURCE_URL);
        if ($source === null) {
            $source = new JobSource(
                $profile,
                'Offres importées manuellement',
                self::SOURCE_URL,
                JobProviderType::MANUAL,
                $profile->getActiveCvDocument(),
            );
            $source->disable();
            $this->entityManager->persist($source);
        }

        $url = trim($data->url);
        $normalizedOffer = new NormalizedJobOffer(
            externalId: hash('sha256', $url),
            url: $url,
            title: trim($data->title),
            company: $this->nullableText($data->company),
            location: $this->nullableText($data->location),
            contractType: $this->extractContractType($data->description),
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: preg_match('/télétravail|remote|hybride/iu', $data->description) === 1 ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: null,
            description: trim($data->description),
            publishedAt: null,
            validThrough: null,
            rawPayload: ['source' => 'manual'],
        );

        $offer = $this->offerRepository->findOneBySourceAndExternalId($source, $normalizedOffer->externalId);
        if ($offer === null) {
            $offer = new JobOffer($source, $normalizedOffer);
            $this->entityManager->persist($offer);
        } else {
            $offer->updateFrom($normalizedOffer);
        }

        $match = $this->matchService->match($profile, $offer);
        $match->queueSemanticAnalysis();
        $this->entityManager->flush();

        $matchId = $match->getId();
        if ($matchId === null) {
            throw new \LogicException('A persisted manual job match must have an identifier.');
        }
        $this->messageBus->dispatch(new AnalyzeJobMatchMessage($matchId));

        return $match;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function extractContractType(string $description): ?string
    {
        if (preg_match('/\b(CDI|CDD|freelance|stage|alternance|intérim)\b/iu', $description, $matches) !== 1) {
            return null;
        }

        return mb_strtoupper($matches[1]);
    }
}
