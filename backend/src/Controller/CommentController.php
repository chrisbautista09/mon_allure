<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
final class CommentController extends AbstractController
{
    #[Route('/comments/new', name: 'app_comment_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $comment = (new Comment())
            ->setUser($user)
            ->setContent($request->request->getString('content'));
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_comment', $request->request->getString('_token'))) {
                $errors[] = 'Le formulaire a expiré. Veuillez réessayer.';
            } else {
                foreach ($validator->validate($comment) as $violation) {
                    $errors[] = $violation->getMessage();
                }
            }

            if ($errors === []) {
                $entityManager->persist($comment);
                $entityManager->flush();
                $this->addFlash('success', 'Votre commentaire a bien été transmis à l’administrateur.');

                return $this->redirectToRoute('app_comment_new');
            }
        }

        return $this->render('comment/new.html.twig', [
            'content' => $comment->getContent(),
            'errors' => array_values(array_unique($errors)),
        ]);
    }
}
