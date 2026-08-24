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

    public function testAuthenticatedUserWithoutProfileCannotUpdateProfile(): void
    {
        $this->authenticateUser();

        $this->client->jsonRequest('PUT', '/api/profile', ['vma' => 16.2]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertResponseJsonContains(['message' => 'Profil physiologique introuvable.']);
    }

    public function testAnonymousUserCannotUpdateProfile(): void
    {
        $this->client->jsonRequest('PUT', '/api/profile', ['vma' => 16.2]);

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testInvalidJsonProfileUpdateIsRejected(): void
    {
        $user = $this->authenticateUser();
        $this->createProfile($user);

        $this->client->request(
            'PUT',
            '/api/profile',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid-json'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertResponseJsonContains([
            'message' => 'Le corps de la requête doit contenir un JSON valide.',
        ]);
    }

    public function testEmptyProfileUpdateIsRejected(): void
    {
        $user = $this->authenticateUser();
        $this->createProfile($user);

        $this->client->jsonRequest('PUT', '/api/profile', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertResponseJsonContains(['message' => 'Aucune donnée de profil à modifier.']);
    }

    public function testInvalidProfileUpdateTypesAreRejected(): void
    {
        $user = $this->authenticateUser();
        $this->createProfile($user);

        $this->client->jsonRequest('PUT', '/api/profile', [
            'firstName' => [],
            'age' => '33',
            'vma' => '16.2',
            'fcm' => 192.5,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $this->responseData();
        self::assertSame(['firstName', 'age', 'vma', 'fcm'], array_keys($response['errors']));
    }

    public function testUserCannotUpdateAnotherUsersProfile(): void
    {
        $owner = $this->authenticateUser();
        $ownerProfile = $this->createProfile($owner);

        $otherUser = (new User())
            ->setEmail('alex@example.com')
            ->setPseudo('alex')
            ->setPassword('test-password');
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->jsonRequest('PUT', '/api/profile', ['vma' => 20.0]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->entityManager->clear();

        $unchangedProfile = $this->entityManager->getRepository(Profile::class)->find($ownerProfile->getId());
        self::assertInstanceOf(Profile::class, $unchangedProfile);
        self::assertSame(15.5, $unchangedProfile->getVma());
    }

    public function testAuthenticatedUserCanUpdateOwnProfile(): void
    {
        $user = $this->authenticateUser();
        $this->createProfile($user);

        $this->client->jsonRequest('PUT', '/api/profile', [
            'age' => 33,
            'vma' => 16.2,
            'fcm' => 192,
            'fcr' => null,
        ]);

        self::assertResponseIsSuccessful();
        $this->assertResponseJsonContains([
            'firstName' => 'Camille',
            'lastName' => 'Martin',
            'age' => 33,
            'vma' => 16.2,
            'fcm' => 192,
            'fcr' => null,
        ]);

        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy([]);
        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame(33, $profile->getAge());
        self::assertSame(16.2, $profile->getVma());
    }

    public function testInvalidProfileUpdateIsRejectedAndNotPersisted(): void
    {
        $user = $this->authenticateUser();
        $this->createProfile($user);

        $this->client->jsonRequest('PUT', '/api/profile', [
            'age' => 12,
            'vma' => 42,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertResponseJsonContains(['message' => 'Les données du profil sont invalides.']);

        $this->entityManager->clear();
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy([]);
        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame(32, $profile->getAge());
        self::assertSame(15.5, $profile->getVma());
    }

    public function testProfileUpdateAutomaticallyRefreshesUpdatedAt(): void
    {
        $user = $this->authenticateUser();
        $oldTimestamp = new \DateTimeImmutable('2020-01-01 00:00:00');
        $profile = $this->createProfile($user);
        $profileId = $profile->getId();
        self::assertNotNull($profileId);

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE profile SET updated_at = :updatedAt WHERE id = :id',
            ['updatedAt' => $oldTimestamp->format('Y-m-d H:i:s'), 'id' => $profileId]
        );
        $this->entityManager->clear();

        $this->client->jsonRequest('PUT', '/api/profile', ['vma' => 16.8]);

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();

        $updatedProfile = $this->entityManager->getRepository(Profile::class)->find($profileId);
        self::assertInstanceOf(Profile::class, $updatedProfile);
        self::assertGreaterThan($oldTimestamp, $updatedProfile->getUpdatedAt());
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

    private function createProfile(User $user): Profile
    {
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15.5)
            ->setVo2max(48.2)
            ->setFcm(190)
            ->setFcr(55)
            ->setUser($user);

        $user->setProfile($profile);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
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
