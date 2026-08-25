<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PhysiologicalProfileController extends AbstractController
{
    #[Route('/profile/calibration', name: 'app_profile_calibration', methods: ['GET', 'POST'])]
    public function calibration(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        if ($user->getProfile() !== null) {
            $this->addFlash('info', 'Votre profil physiologique est déjà renseigné.');

            return $this->redirectToRoute(
                $this->hasActiveTrainingPlan($user) ? 'app_training_weekly' : 'app_training_goal',
            );
        }

        $profile = (new Profile())
            ->setUser($user)
            ->setUpdatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setProfile($profile);
            $entityManager->persist($profile);
            $entityManager->flush();

            $this->addFlash('success', 'Votre profil physiologique a bien été enregistré.');

            return $this->redirectToRoute('app_training_goal');
        }

        return $this->render('profile/calibration.html.twig', [
            'profileForm' => $form,
        ]);
    }

    private function hasActiveTrainingPlan(User $user): bool
    {
        foreach ($user->getTrainingPlans() as $plan) {
            if ($plan->isActive()) {
                return true;
            }
        }

        return false;
    }
}
