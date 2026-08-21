<?php

declare(strict_types=1);

namespace App\Matching\DTO;

final readonly class CvOptimizationReport
{
    /**
     * @param list<CvOptimizationItem> $strengthsToHighlight
     * @param list<CvOptimizationItem> $detailsToImprove
     * @param list<CvOptimizationItem> $unmentionedToVerify
     * @param list<CvOptimizationItem> $skillsToDevelop
     */
    public function __construct(
        public int $analyzedOfferCount,
        public array $strengthsToHighlight,
        public array $detailsToImprove,
        public array $unmentionedToVerify,
        public array $skillsToDevelop,
    ) {
    }

    public static function empty(): self
    {
        return new self(0, [], [], [], []);
    }
}
