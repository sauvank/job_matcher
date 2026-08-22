<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Turbo\TurboBundle;

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

        self::assertResponseRedirects('/profile#skills');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.skill-management-list', 'Laravel '.$uniqueId);
        self::assertSelectorExists('.skill-level-field option[value="ADVANCED"][selected]');
        self::assertSelectorExists('#skill-level-filter option[value="ADVANCED"]');
        self::assertSelectorExists('.skill-management-row[data-skill-level="ADVANCED"]');
        self::assertSelectorExists('.empty-filtered-skills[hidden]');

        $levelSelect = $crawler->filter('.skill-level-field select');
        $skillIdValue = str_replace(['levels[', ']'], '', (string) $levelSelect->attr('name'));
        self::assertTrue(ctype_digit($skillIdValue));
        $skillId = (int) $skillIdValue;
        $token = (string) $crawler->filter('.skill-levels-form input[name="_token"]')->attr('value');
        $client->request('POST', '/profile/skills/levels', [
            '_token' => $token,
            'levels' => [$skillId => 'EXPERT'],
        ], [], ['HTTP_ACCEPT' => TurboBundle::STREAM_MEDIA_TYPE]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', TurboBundle::STREAM_MEDIA_TYPE.'; charset=UTF-8');
        self::assertSelectorTextContains('turbo-stream[target="skill-feedback"]', 'Tous les niveaux ont été enregistrés.');

        $crawler = $client->request('GET', '/profile');
        self::assertSelectorExists('.skill-level-field option[value="EXPERT"][selected]');

        $deleteForm = $crawler->filter('form[action$="/delete"]')->form();
        $client->submit($deleteForm);
        self::assertResponseRedirects('/profile#skills');
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
        $ownerSkill = new Skill('Compétence du propriétaire '.$uniqueId, 'owner-skill-'.$uniqueId, SkillCategory::OTHER);
        $ownerCandidateSkill = new CandidateSkill($owner->getCandidateProfile(), $ownerSkill);
        $entityManager->persist($skill);
        $entityManager->persist($candidateSkill);
        $entityManager->persist($ownerSkill);
        $entityManager->persist($ownerCandidateSkill);
        $entityManager->flush();
        $candidateSkillId = $candidateSkill->getId();
        self::assertNotNull($candidateSkillId);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/profile');
        $token = (string) $crawler->filter('.skill-levels-form input[name="_token"]')->attr('value');
        $client->request('POST', '/profile/skills/levels', [
            '_token' => $token,
            'levels' => [$candidateSkillId => 'EXPERT'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
