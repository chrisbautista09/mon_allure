<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminSecurityTest extends WebTestCase
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

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testRegularUserCannotAccessAdministration(): void
    {
        $this->client->loginUser($this->persist($this->user()));

        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanAccessAdministrationAndInheritsUserRole(): void
    {
        $administrator = $this->persist($this->user()->setRoles(['ROLE_ADMIN']));
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-dashboard"]');
        self::assertSelectorTextContains('#admin-title', 'Gestion des utilisateurs');
        self::assertSelectorExists('[data-admin-users][data-admin-users-url="/api/admin/users"]');
        self::assertSelectorExists('[data-admin-users][data-admin-users-csrf]:not([data-admin-users-csrf=""])');
        self::assertSelectorExists('[data-admin-users][data-admin-current-user-id="'.$administrator->getId().'"]');
        self::assertSelectorExists('[data-admin-users-feedback][role="status"]');
        self::assertSelectorExists('form[data-admin-users-filters]');
        self::assertSelectorExists('input[type="search"][data-admin-users-search]');
        self::assertSelectorCount(3, '[data-admin-users-status-filter] option');
        self::assertSelectorCount(3, '[data-admin-users-role-filter] option');
        self::assertSelectorCount(6, '[data-testid="admin-users-table"] thead th');
        self::assertSelectorExists('[data-admin-users-table][hidden]');
        self::assertSelectorExists('[data-admin-users-empty][hidden]');
        self::assertSelectorExists('[data-admin-users-pagination][hidden]');
        self::assertContains('ROLE_ADMIN', $administrator->getRoles());
        self::assertContains('ROLE_USER', $administrator->getRoles());
    }

    private function user(): User
    {
        return (new User())
            ->setEmail('security-'.uniqid().'@example.com')
            ->setPseudo('security-'.uniqid())
            ->setPassword('test-password');
    }

    private function persist(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
