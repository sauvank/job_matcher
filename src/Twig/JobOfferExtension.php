<?php

declare(strict_types=1);

namespace App\Twig;

use App\Job\Application\Service\EsnDetector;
use App\Job\Entity\JobOffer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class JobOfferExtension extends AbstractExtension
{
    public function __construct(
        private readonly EsnDetector $esnDetector,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_esn', $this->isEsn(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('is_esn', $this->isEsn(...)),
        ];
    }

    public function isEsn(JobOffer $offer): bool
    {
        return $this->esnDetector->isEsn($offer);
    }
}
