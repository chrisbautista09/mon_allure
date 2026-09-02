<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CommentControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testUserCanSendOneWayCommentToAdministration(): void
    {
        $user = $this->persistUser('runner@example.com', 'runner');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/comments/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="user-comment-form"]');

        $form = $crawler->selectButton('Transmettre à l’administrateur')->form([
            'content' => 'Je souhaite signaler une difficulté avec mon programme.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/comments/new');
        $comment = $this->entityManager->getRepository(Comment::class)->findOneBy(['user' => $user]);
        self::assertNotNull($comment);
        self::assertSame('Je souhaite signaler une difficulté avec mon programme.', $comment->getContent());
        self::assertNull($comment->getSession());
        self::assertNull($comment->getTrainingPlan());
    }

    public function testAdministratorCanReadCommentsButRegularUserCannot(): void
    {
        $author = $this->persistUser('author@example.com', 'auteur');
        $comment = (new Comment())->setUser($author)->setContent('Suggestion visible uniquement par un administrateur.');
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->client->loginUser($author);
        $this->client->request('GET', '/admin/comments');
        self::assertResponseStatusCodeSame(403);

        $administrator = $this->persistUser('admin@example.com', 'admin')->setRoles(['ROLE_ADMIN']);
        $this->entityManager->flush();
        $this->client->loginUser($administrator);
        $this->client->request('GET', '/admin/comments');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-comments"]');
        self::assertSelectorTextContains('[data-comment-id="'.$comment->getId().'"]', 'Suggestion visible uniquement par un administrateur.');
        self::assertSelectorNotExists('form[action*="comment"]');
    }

    private function persistUser(string $email, string $pseudo): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
