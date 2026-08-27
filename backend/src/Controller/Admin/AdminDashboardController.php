<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig');
    }

    #[Route('/algorithm-parameters', name: 'algorithm_parameters', methods: ['GET'])]
    public function algorithmParameters(): Response
    {
        return $this->render('admin/algorithm_parameters/index.html.twig');
    }

    #[Route('/training-plans', name: 'training_plans', methods: ['GET'])]
    public function trainingPlans(): Response
    {
        return $this->render('admin/training_plans/index.html.twig');
    }
}
