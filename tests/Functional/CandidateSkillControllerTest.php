<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use Doctrine\ORM\EntityManagerInterface;

final class CandidateSkillControllerTest extends AuthenticatedWebTestCase
{
    public function testAccountCanAddUpdateAndDeleteItsSkill(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('skills-'.$uniqueId.'@example.test');
        $client->loginUser($account);

        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Ajouter')->form([
            'candidate_skill[name]' => 'Laravel '.$uniqueId,
            'candidate_skill[level]' => 'ADVANCED',
            'candidate_skill[category]' => 'BACKEND',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/profile');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.skill-management-list', 'Laravel '.$uniqueId);
        self::assertSelectorExists('.skill-level-form option[value="ADVANCED"][selected]');

        $levelForm = $crawler->filter('.skill-level-form')->form(['level' => 'EXPERT']);
        $client->submit($levelForm);
        self::assertResponseRedirects('/profile');
        $crawler = $client->followRedirect();
        self::assertSelectorExists('.skill-level-form option[value="EXPERT"][selected]');

        $deleteForm = $crawler->filter('.skill-management-row form[action$="/delete"]')->form();
        $client->submit($deleteForm);
        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertSelectorTextNotContains('body', 'Laravel '.$uniqueId);
    }

    public function testAccountCannotManageAnotherAccountsSkill(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $owner = $this->account('skills-owner-'.$uniqueId.'@example.test');
        $otherAccount = $this->account('skills-other-'.$uniqueId.'@example.test');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $skill = new Skill('Compétence privée '.$uniqueId, 'private-skill-'.$uniqueId, SkillCategory::OTHER);
        $candidateSkill = new CandidateSkill($otherAccount->getCandidateProfile(), $skill);
        $entityManager->persist($skill);
        $entityManager->persist($candidateSkill);
        $entityManager->flush();
        $candidateSkillId = $candidateSkill->getId();
        self::assertNotNull($candidateSkillId);
        $client->loginUser($owner);

        $client->request('POST', '/profile/skills/'.$candidateSkillId.'/level', ['level' => 'EXPERT']);

        self::assertResponseStatusCodeSame(404);
    }
}
