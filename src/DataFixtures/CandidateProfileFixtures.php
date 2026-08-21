<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class CandidateProfileFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur backend PHP/Symfony', 'Paris', 6, 'CV de démonstration PHP Symfony Docker PostgreSQL PHPUnit.');

        foreach ([
            ['PHP', 'php', SkillCategory::BACKEND, true],
            ['Symfony', 'symfony', SkillCategory::BACKEND, true],
            ['PostgreSQL', 'postgresql', SkillCategory::DATABASE, false],
            ['Docker', 'docker', SkillCategory::DEVOPS, false],
        ] as [$name, $normalizedName, $category, $isCore]) {
            $skill = new Skill($name, $normalizedName, $category);
            new CandidateSkill($profile, $skill, SkillLevel::ADVANCED, null, $isCore, 1.0);
            $manager->persist($skill);
        }

        $manager->persist($profile);
        $manager->flush();
    }
}
