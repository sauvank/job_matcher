<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Entity;

use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use PHPUnit\Framework\TestCase;

final class JobSourceTest extends TestCase
{
    public function testItUpdatesItsGeneratedSearch(): void
    {
        $source = new JobSource('Ancienne recherche', 'https://example.test/old', JobProviderType::HELLOWORK);
        $source->markSyncStarted();
        $source->completeSync();

        $source->updateSearch('HelloWork — Développeur PHP — Paris', 'https://example.test/new');

        self::assertSame('HelloWork — Développeur PHP — Paris', $source->getName());
        self::assertSame('https://example.test/new', $source->getUrl());
        self::assertNull($source->getLastSyncStartedAt());
        self::assertNull($source->getLastSuccessAt());
        self::assertNull($source->getLastError());
    }
}
