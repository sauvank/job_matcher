<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Enum;

use App\Matching\Enum\JobApplicationStatus;
use PHPUnit\Framework\TestCase;

final class JobApplicationStatusTest extends TestCase
{
    public function testCasesHaveLabelsIconsAndBadgeClasses(): void
    {
        $expected = [
            'UNPROCESSED' => ['label' => 'À traiter', 'icon' => '📋', 'badge' => 'badge-neutral'],
            'INTERESTED' => ['label' => 'M’intéresse', 'icon' => '⭐', 'badge' => 'badge-info'],
            'NOT_INTERESTED' => ['label' => 'Ne m’intéresse pas', 'icon' => '🚫', 'badge' => 'badge-neutral'],
            'APPLIED' => ['label' => 'Candidaté', 'icon' => '📨', 'badge' => 'badge-primary'],
            'WAITING' => ['label' => 'En attente', 'icon' => '⏳', 'badge' => 'badge-warning'],
            'INTERVIEW' => ['label' => 'Entretien', 'icon' => '🗣️', 'badge' => 'badge-warning'],
            'REJECTED' => ['label' => 'Refusé', 'icon' => '❌', 'badge' => 'badge-danger'],
            'ACCEPTED' => ['label' => 'Accepté', 'icon' => '🎉', 'badge' => 'badge-success'],
        ];

        self::assertCount(count($expected), JobApplicationStatus::cases());

        foreach (JobApplicationStatus::cases() as $status) {
            $details = $expected[$status->value];
            self::assertSame($details['label'], $status->label());
            self::assertSame($details['icon'], $status->icon());
            self::assertSame($details['badge'], $status->badgeClass());
        }
    }
}
