<?php

declare(strict_types=1);

namespace App\Candidate\Application\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class SkillNameNormalizer
{
    public function normalize(string $name): string
    {
        return (new AsciiSlugger())->slug(trim($name))->lower()->toString();
    }
}
