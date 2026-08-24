<?php

namespace App\Tests\Functional;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProfileControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessProfileApi(): void
    {
        $this->client->request('GET', '/api/profile');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAuthenticatedUserWithoutProfileGetsNotFoundResponse(): void
    {
        $this->authenticateUser();

        $this->client->request('GET', '/api/profile');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertResponseJsonContains(['message' => 'Profil physiologique introuvable.']);
    }

    public function testAuthenticatedUserCanCreateAndReadOwnProfile(): void
    {
        $this->authenticateUser();

        $this->client->jsonRequest('POST', '/api/profile', $this->validPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseJsonContains([
            'firstName' => 'Camille',
            'lastName' => 'Martin',
            'age' => 32,
            'vma' => 15.5,
            'fcm' => 190,
            'fcr' => 55,
        ]);

        $this->client->request('GET', '/api/profile');

        self::assertResponseIsSuccessful();
        $this->assertResponseJsonContains([
            'firstName' => 'Camille',
            'lastName' => 'Martin',
        ]);
    }

    public function testInvalidPhysiologicalValuesAreRejected(): void
    {
        $this->authenticateUser();
        $payload = $this->validPayload();
        $payload['age'] = 17;
        $payload['vma'] = 31;
        $payload['fcm'] = 99;
        $payload['fcr'] = 121;

        $this->client->jsonRequest('POST', '/api/profile', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertResponseJsonContains(['message' => 'Les données du profil sont invalides.']);

        $response = $this->responseData();
        self::assertSame(['age', 'vma', 'fcm', 'fcr'], array_keys($response['errors']));
        self::assertSame(0, $this->entityManager->getRepository(Profile::class)->count([]));
    }

    public function testUserCannotCreateASecondProfile(): void
    {
        $this->authenticateUser();

        $this->client->jsonRequest('POST', '/api/profile', $this->validPayload());
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->jsonRequest('POST', '/api/profile', $this->validPayload());

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertResponseJsonContains(['message' => 'Un profil physiologique existe déjà.']);
        self::assertSame(1, $this->entityManager->getRepository(Profile::class)->count([]));
    }

    private function authenticateUser(): User
    {
        $user = (new User())
            ->setEmail('camille@example.com')
            ->setPseudo('camille')
            ->setPassword('test-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        return $user;
    }

    /** @param array<string, mixed> $expected */
    private function assertResponseJsonContains(array $expected): void
    {
        $response = $this->responseData();

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $response);
            self::assertSame($value, $response[$key]);
        }
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertIsArray($data);

        return $data;
    }

    /** @return array<string, int|float|string> */
    private function validPayload(): array
    {
        return [
            'firstName' => 'Camille',
            'lastName' => 'Martin',
            'age' => 32,
            'vma' => 15.5,
            'vo2max' => 48.2,
            'fcm' => 190,
            'fcr' => 55,
        ];
    }
}
