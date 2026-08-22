<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Security\Entity\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CandidateProfileFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $account = new Account('demo@example.test');
        $account->setPassword($this->passwordHasher->hashPassword($account, 'demo-password-change-me'));
        $profile = $account->getCandidateProfile();
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

        $manager->persist($account);
        $manager->flush();
    }
}
