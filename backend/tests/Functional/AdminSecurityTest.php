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

    public function testAlgorithmParametersPageIsRestrictedAndDisplaysAllGroupedFields(): void
    {
        $this->client->request('GET', '/admin/algorithm-parameters');
        self::assertResponseRedirects('http://localhost/login');

        $this->client->loginUser($this->persist($this->user()));
        $this->client->request('GET', '/admin/algorithm-parameters');
        self::assertResponseStatusCodeSame(403);

        $administrator = $this->persist($this->user()->setRoles(['ROLE_ADMIN']));
        $this->client->loginUser($administrator);
        $this->client->request('GET', '/admin/algorithm-parameters');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-algorithm-parameters"]');
        self::assertSelectorTextContains('#algorithm-parameters-title', 'Paramètres de l’algorithme');
        self::assertSelectorCount(4, 'nav[aria-label="Navigation de l’administration"] a');
        self::assertSelectorCount(6, '[data-parameter-category]');
        self::assertSelectorCount(14, 'input[data-algorithm-parameter-key][type="number"][required]');
        self::assertSelectorExists('[data-parameter-category="progression"]');
        self::assertSelectorExists('[data-parameter-category="volume"]');
        self::assertSelectorExists('[data-parameter-category="sessions"]');
        self::assertSelectorExists('[data-parameter-category="coefficients"]');
        self::assertSelectorExists('[data-parameter-category="validation"]');
        self::assertSelectorExists('[data-parameter-category="duration"]');
        self::assertSelectorExists('[data-admin-algorithm-parameters-form][hidden]');
        self::assertSelectorExists('[data-admin-algorithm-parameters-save][disabled]');
    }

    public function testTrainingPlansMonitoringPageIsRestrictedAndResponsive(): void
    {
        $this->client->request('GET', '/admin/training-plans');
        self::assertResponseRedirects('http://localhost/login');

        $this->client->loginUser($this->persist($this->user()));
        $this->client->request('GET', '/admin/training-plans');
        self::assertResponseStatusCodeSame(403);

        $administrator = $this->persist($this->user()->setRoles(['ROLE_ADMIN']));
        $this->client->loginUser($administrator);
        $this->client->request('GET', '/admin/training-plans');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-training-plans"]');
        self::assertSelectorTextContains('#training-plans-title', 'Plans d’entraînement générés');
        self::assertSelectorExists('[data-admin-training-plans][data-admin-training-plans-url="/api/admin/training-plans"]');
        self::assertSelectorCount(4, 'nav[aria-label="Navigation de l’administration"] a');
        self::assertSelectorCount(8, '[data-testid="admin-training-plans-table"] thead th');
        self::assertSelectorExists('form[data-admin-training-plans-filters]');
        self::assertSelectorExists('input[type="search"][data-admin-training-plans-user]');
        self::assertSelectorCount(4, '[data-admin-training-plans-feasibility] option');
        self::assertSelectorCount(4, '[data-admin-training-plans-status-filter] option');
        self::assertSelectorExists('[data-admin-training-plans-table][hidden].overflow-x-auto');
        self::assertSelectorExists('[data-admin-training-plans-empty][hidden]');
        self::assertSelectorExists('[data-admin-training-plans-pagination][hidden]');
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
