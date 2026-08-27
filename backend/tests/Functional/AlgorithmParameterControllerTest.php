<?php

namespace App\Tests\Functional;

use App\DataFixtures\AlgorithmParameterFixtures;
use App\Entity\AlgorithmParameter;
use App\Entity\User;
use App\Repository\AlgorithmParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AlgorithmParameterControllerTest extends WebTestCase
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
        $repository = self::getContainer()->get(AlgorithmParameterRepository::class);
        (new AlgorithmParameterFixtures($repository))->load($this->entityManager);
    }

    public function testAnonymousAndRegularUserCannotReadParameters(): void
    {
        $this->client->request('GET', '/api/admin/algorithm-parameters');
        self::assertResponseRedirects('http://localhost/login');

        $this->client->loginUser($this->persistUser('regular-parameters@example.com'));
        $this->client->request('GET', '/api/admin/algorithm-parameters');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorReadsParametersAsJson(): void
    {
        $this->client->loginUser($this->persistUser('admin-read-parameters@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/admin/algorithm-parameters');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = $this->responseData();
        self::assertCount(14, $response['parameters']);
        self::assertSame(2, $response['parameters'][AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY]);
        self::assertNotEmpty($response['csrfToken']);
    }

    public function testAdministratorUpdatesParametersAndReceivesPersistedValues(): void
    {
        $this->client->loginUser($this->persistUser('admin-update-parameters@example.com', ['ROLE_ADMIN']));
        $token = $this->csrfToken();

        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', [
            'parameters' => [
                AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 8,
                AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => 3,
            ],
        ], ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertTrue($response['success']);
        self::assertSame(8, $response['parameters'][AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT]);
        self::assertSame(3, $response['parameters'][AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY]);
        $this->entityManager->clear();
        $progression = $this->entityManager->getRepository(AlgorithmParameter::class)->findOneBy([
            'parameterKey' => AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT,
        ]);
        self::assertInstanceOf(AlgorithmParameter::class, $progression);
        self::assertSame(8.0, $progression->getParameterValue());
        $logs = file_get_contents($this->adminLogPath());
        self::assertIsString($logs);
        self::assertStringContainsString('admin-update-parameters@example.com', $logs);
        self::assertStringContainsString('"parameter":"progression_max_percent"', $logs);
        self::assertStringContainsString('"old_value":10.0', $logs);
        self::assertStringContainsString('"new_value":8.0', $logs);
    }

    public function testUpdateRequiresAdministratorAndValidCsrfToken(): void
    {
        $regularUser = $this->persistUser('regular-update-parameters@example.com');
        $this->client->loginUser($regularUser);
        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', ['parameters' => []]);
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->persistUser('admin-csrf-parameters@example.com', ['ROLE_ADMIN']));
        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', ['parameters' => []]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateRejectsInvalidJsonShapeAndParameterValue(): void
    {
        $this->client->loginUser($this->persistUser('admin-invalid-parameters@example.com', ['ROLE_ADMIN']));
        $token = $this->csrfToken();

        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', ['unexpected' => []], [
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', [
            'parameters' => [AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 150],
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrfToken()]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('0 et 30', $this->responseData()['message']);
    }

    public function testUnchangedValueDoesNotCreateFalseHistoryEntry(): void
    {
        $email = 'admin-noop-parameters@example.com';
        $this->client->loginUser($this->persistUser($email, ['ROLE_ADMIN']));

        $this->client->jsonRequest('PUT', '/api/admin/algorithm-parameters', [
            'parameters' => [AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 10],
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrfToken()]);

        self::assertResponseIsSuccessful();
        $logs = file_exists($this->adminLogPath()) ? file_get_contents($this->adminLogPath()) : '';
        self::assertIsString($logs);
        self::assertStringNotContainsString($email, $logs);
    }

    private function persistUser(string $email, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password')
            ->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function csrfToken(): string
    {
        $this->client->request('GET', '/api/admin/algorithm-parameters');
        self::assertResponseIsSuccessful();

        return $this->responseData()['csrfToken'];
    }

    private function adminLogPath(): string
    {
        return self::getContainer()->getParameter('kernel.logs_dir').'/admin_test.log';
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
