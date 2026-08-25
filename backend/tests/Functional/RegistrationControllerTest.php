<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testInvalidRegistrationIsRejectedAndNotPersisted(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'invalid-email',
            'registration_form[pseudo]' => 'a',
            'registration_form[plainPassword][first]' => 'abcdefgh',
            'registration_form[plainPassword][second]' => 'abcdefgh',
            'registration_form[agreeTerms]' => true,
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'adresse e-mail valide');
        self::assertSelectorTextContains('form', 'au moins 3 caractères');
        self::assertSelectorTextContains('form', 'au moins une lettre et un chiffre');
        self::assertSame(0, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testDuplicateEmailAndPseudoAreRejected(): void
    {
        $existing = (new User())
            ->setEmail('existing@example.com')
            ->setPseudo('existing-runner')
            ->setPassword('hashed-password');
        $this->entityManager->persist($existing);
        $this->entityManager->flush();
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'EXISTING@example.com',
            'registration_form[pseudo]' => 'existing-runner',
            'registration_form[plainPassword][first]' => 'SecurePass123',
            'registration_form[plainPassword][second]' => 'SecurePass123',
            'registration_form[agreeTerms]' => true,
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'account with this email');
        self::assertSelectorTextContains('form', 'pseudo est déjà utilisé');
        self::assertSame(1, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testValidRegistrationPersistsUserWithHashedPassword(): void
    {
        $plainPassword = 'SecurePass123';
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'New.Runner@Example.COM ',
            'registration_form[pseudo]' => 'new-runner',
            'registration_form[plainPassword][first]' => $plainPassword,
            'registration_form[plainPassword][second]' => $plainPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '[role="status"]',
            'Votre compte a bien été créé. Vous pouvez maintenant vous connecter.',
        );
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'new.runner@example.com',
        ]);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getId());
        self::assertSame('new-runner', $user->getPseudo());
        self::assertNotNull($user->getCreatedAt());
        self::assertNotSame($plainPassword, $user->getPassword());
        self::assertNotEmpty($user->getPassword());

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, $plainPassword));
        self::assertFalse($passwordHasher->isPasswordValid($user, 'WrongPassword123'));
        self::assertSame(1, $this->entityManager->getRepository(User::class)->count([]));
    }
}
