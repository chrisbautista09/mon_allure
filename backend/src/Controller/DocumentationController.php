<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentationController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/../docs/presentation/cahier-des-charges-synthese.html')]
        private readonly string $specificationPath,
    ) {
    }

    #[Route('/cahier-des-charges', name: 'app_specification', methods: ['GET'])]
    public function specification(): Response
    {
        $content = file_get_contents($this->specificationPath);

        if ($content === false) {
            throw $this->createNotFoundException('Le cahier des charges est introuvable.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
