<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Candidate\Entity\CvDocument;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request, AccountRepository $accountRepository, EntityManagerInterface $entityManager): Response
    {
        $query = $request->query->getString('q');
        $verified = $request->query->getString('verified', 'all');
        $role = $request->query->getString('role', 'all');

        $accounts = $accountRepository->findWithFilters(
            $query !== '' ? $query : null,
            $verified !== 'all' ? $verified : null,
            $role !== 'all' ? $role : null,
        );

        // Compute summary counts per account for quick overview
        $accountStats = [];
        foreach ($accounts as $account) {
            $profile = $account->getCandidateProfile();
            $cvCount = $entityManager->getRepository(CvDocument::class)->count(['candidateProfile' => $profile]);
            $sourceCount = $entityManager->getRepository(JobSource::class)->count(['candidateProfile' => $profile]);
            $skillCount = $profile->getCandidateSkills()->count();

            $accountStats[$account->getId() ?? 0] = [
                'cv_count' => $cvCount,
                'source_count' => $sourceCount,
                'skill_count' => $skillCount,
            ];
        }

        return $this->render('admin/user/index.html.twig', [
            'accounts' => $accounts,
            'account_stats' => $accountStats,
            'filters' => [
                'q' => $query,
                'verified' => $verified,
                'role' => $role,
            ],
            'total_count' => count($accounts),
        ]);
    }

    #[Route('/admin/users/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, AccountRepository $accountRepository, EntityManagerInterface $entityManager): Response
    {
        $account = $accountRepository->find($id);
        if (!$account instanceof Account) {
            throw new NotFoundHttpException('Compte introuvable.');
        }

        $profile = $account->getCandidateProfile();
        $cvDocuments = $entityManager->getRepository(CvDocument::class)->findBy(['candidateProfile' => $profile], ['createdAt' => 'DESC']);
        $jobSources = $entityManager->getRepository(JobSource::class)->findBy(['candidateProfile' => $profile], ['createdAt' => 'DESC']);

        $recentOffers = $entityManager->getRepository(JobOffer::class)->createQueryBuilder('o')
            ->innerJoin('o.source', 's')
            ->where('s.candidateProfile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('o.firstSeenAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('admin/user/show.html.twig', [
            'account' => $account,
            'profile' => $profile,
            'cv_documents' => $cvDocuments,
            'job_sources' => $jobSources,
            'recent_offers' => $recentOffers,
        ]);
    }

    #[Route('/admin/users/{id}/toggle-admin', name: 'app_admin_user_toggle_admin', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleAdmin(int $id, Request $request, AccountRepository $accountRepository, EntityManagerInterface $entityManager): Response
    {
        $account = $accountRepository->find($id);
        if (!$account instanceof Account) {
            throw new NotFoundHttpException('Compte introuvable.');
        }

        if (!$this->isCsrfTokenValid('toggle_admin_'.$id, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Account && $currentUser->getId() === $account->getId() && $account->isAdmin()) {
            $this->addFlash('error', 'Vous ne pouvez pas révoquer vos propres privilèges d\'administrateur.');

            return $this->redirectToRoute('app_admin_users');
        }

        $account->toggleAdmin();
        $entityManager->flush();

        $this->addFlash(
            'success',
            $account->isAdmin()
                ? sprintf('Le compte %s a été promu administrateur.', $account->getEmail())
                : sprintf('Les droits administrateur ont été retirés pour %s.', $account->getEmail()),
        );

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/admin/users/{id}/verify-email', name: 'app_admin_user_verify_email', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verifyEmail(int $id, Request $request, AccountRepository $accountRepository, EntityManagerInterface $entityManager): Response
    {
        $account = $accountRepository->find($id);
        if (!$account instanceof Account) {
            throw new NotFoundHttpException('Compte introuvable.');
        }

        if (!$this->isCsrfTokenValid('verify_email_'.$id, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_users');
        }

        $account->verifyEmail();
        $entityManager->flush();

        $this->addFlash('success', sprintf('L\'adresse email de %s a été validée.', $account->getEmail()));

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, AccountRepository $accountRepository, EntityManagerInterface $entityManager): Response
    {
        $account = $accountRepository->find($id);
        if (!$account instanceof Account) {
            throw new NotFoundHttpException('Compte introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_user_'.$id, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Account && $currentUser->getId() === $account->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis le panneau d\'administration.');

            return $this->redirectToRoute('app_admin_users');
        }

        $email = $account->getEmail();
        $entityManager->remove($account);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le compte %s et ses données associées ont été supprimés.', $email));

        return $this->redirectToRoute('app_admin_users');
    }
}
