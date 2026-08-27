<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminUserControllerTest extends WebTestCase
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

    public function testAnonymousAndRegularUsersCannotListAccounts(): void
    {
        $this->client->request('GET', '/api/admin/users');
        self::assertResponseRedirects('http://localhost/login');

        $regularUser = $this->persistUser('regular-admin-api@example.com');
        $this->client->loginUser($regularUser);
        $this->client->request('GET', '/api/admin/users');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanListAndSearchPaginatedUsersWithoutPasswords(): void
    {
        $administrator = $this->persistUser('admin-list@example.com', ['ROLE_ADMIN']);
        $this->persistUser('runner-one@example.com');
        $this->persistUser('runner-two@example.com');
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/users?search=runner&page=1&perPage=1');

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertCount(1, $response['items']);
        self::assertSame(2, $response['pagination']['total']);
        self::assertSame(2, $response['pagination']['totalPages']);
        self::assertArrayNotHasKey('password', $response['items'][0]);
        self::assertSame(['ROLE_USER'], $response['items'][0]['roles']);
    }

    public function testAdministratorCanDeactivateAndReactivateUser(): void
    {
        $administrator = $this->persistUser('admin-update@example.com', ['ROLE_ADMIN']);
        $target = $this->persistUser('target-update@example.com');
        $this->client->loginUser($administrator);

        $this->patchUser($target, ['isActive' => false]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseData()['success']);
        self::assertFalse($this->responseData()['user']['isActive']);

        $this->patchUser($target, ['isActive' => true]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseData()['user']['isActive']);
    }

    public function testPatchRejectsUnknownFieldsAndMissingUser(): void
    {
        $administrator = $this->persistUser('admin-invalid-patch@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($administrator);

        $this->patchUser($administrator, ['roles' => ['ROLE_ADMIN']]);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('PATCH', '/api/admin/users/999999', ['isActive' => true], $this->csrfHeader());
        self::assertResponseStatusCodeSame(404);
    }

    public function testPatchRequiresAValidCsrfToken(): void
    {
        $administrator = $this->persistUser('admin-csrf@example.com', ['ROLE_ADMIN']);
        $target = $this->persistUser('target-csrf@example.com');
        $this->client->loginUser($administrator);

        $this->client->jsonRequest('PATCH', '/api/admin/users/'.$target->getId(), ['isActive' => false]);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($target->isActive());
    }

    public function testAdministratorCanDeleteAnotherUserButNotOwnAccount(): void
    {
        $administrator = $this->persistUser('admin-delete@example.com', ['ROLE_ADMIN']);
        $target = $this->persistUser('target-delete@example.com');
        $targetId = $target->getId();
        $this->client->loginUser($administrator);

        $this->client->request('DELETE', '/api/admin/users/'.$targetId, server: $this->csrfHeader());
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true], $this->responseData());
        self::assertNull($this->entityManager->find(User::class, $targetId));

        $this->client->request('DELETE', '/api/admin/users/'.$administrator->getId(), server: $this->csrfHeader());
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('propre compte', $this->responseData()['message']);
    }

    public function testDeletingUserAlsoDeletesRelatedTrainingDataAndRemovesAccountFromList(): void
    {
        $administrator = $this->persistUser('admin-cascade@example.com', ['ROLE_ADMIN']);
        $target = $this->persistUser('target-cascade@example.com');
        $relatedIds = $this->attachRelatedData($target);
        $targetId = $target->getId();
        $this->client->loginUser($administrator);

        $this->client->request('DELETE', '/api/admin/users/'.$targetId, server: $this->csrfHeader());

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(User::class, $targetId));
        self::assertNull($this->entityManager->find(Profile::class, $relatedIds['profile']));
        self::assertNull($this->entityManager->find(TrainingPlan::class, $relatedIds['plan']));
        self::assertNull($this->entityManager->find(Session::class, $relatedIds['session']));
        self::assertNull($this->entityManager->find(Performance::class, $relatedIds['performance']));
        self::assertNull($this->entityManager->find(Comment::class, $relatedIds['comment']));

        $this->client->request('GET', '/api/admin/users?search=target-cascade@example.com');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData()['items']);
    }

    public function testInvalidPaginationReturnsUnprocessableEntity(): void
    {
        $administrator = $this->persistUser('admin-pagination@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/users?page=0&perPage=200');

        self::assertResponseStatusCodeSame(422);
    }

    public function testAdministratorCanCombineSearchStatusAndRoleFilters(): void
    {
        $administrator = $this->persistUser('admin-filter@example.com', ['ROLE_ADMIN']);
        $this->persistUser('active-runner@example.com');
        $this->persistUser('inactive-runner@example.com')->setIsActive(false);
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/users?search=runner&status=inactive&role=user');

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(1, $response['pagination']['total']);
        self::assertSame('inactive-runner@example.com', $response['items'][0]['email']);
    }

    public function testInvalidAdministrativeFilterReturnsUnprocessableEntity(): void
    {
        $administrator = $this->persistUser('admin-invalid-filter@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/users?role=super-admin');

        self::assertResponseStatusCodeSame(422);
    }

    public function testPaginationHandlesOneThousandUsers(): void
    {
        $administrator = $this->persistUser('admin-thousand@example.com', ['ROLE_ADMIN']);
        for ($index = 1; $index < 1000; ++$index) {
            $user = (new User())
                ->setEmail(sprintf('runner-%04d@example.com', $index))
                ->setPseudo(sprintf('runner-%04d', $index))
                ->setPassword('test-password');
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/users?page=10&perPage=100');

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(1000, $response['pagination']['total']);
        self::assertSame(10, $response['pagination']['totalPages']);
        self::assertSame(10, $response['pagination']['page']);
        self::assertCount(100, $response['items']);
    }

    /** @param list<string> $roles */
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

    /** @return array{profile: int, plan: int, session: int, performance: int, comment: int} */
    private function attachRelatedData(User $user): array
    {
        $profile = (new Profile())
            ->setFirstName('Alex')
            ->setLastName('Martin')
            ->setAge(35)
            ->setUser($user);
        $user->setProfile($profile);
        $plan = (new TrainingPlan())
            ->setName('Plan à supprimer')
            ->setPoleType('discovery')
            ->setTargetType('distance')
            ->setTargetValue(5)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-26'))
            ->setDurationWeeks(8);
        $session = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(2)
            ->setTitle('Séance liée')
            ->setSessionType('endurance')
            ->setDate(new \DateTimeImmutable('2026-09-01'));
        $performance = (new Performance())
            ->setDistanceKm(5)
            ->setDurationSec(1800)
            ->setSession($session);
        $comment = (new Comment())->setContent('Commentaire lié')->setSession($session);
        $plan->addSession($session);
        $user->addTrainingPlan($plan)->addPerformance($performance)->addComment($comment);
        $this->entityManager->flush();

        return [
            'profile' => $profile->getId(),
            'plan' => $plan->getId(),
            'session' => $session->getId(),
            'performance' => $performance->getId(),
            'comment' => $comment->getId(),
        ];
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

    /** @param array<string, mixed> $data */
    private function patchUser(User $user, array $data): void
    {
        $this->client->jsonRequest('PATCH', '/api/admin/users/'.$user->getId(), $data, $this->csrfHeader());
    }

    /** @return array<string, string> */
    private function csrfHeader(): array
    {
        $crawler = $this->client->request('GET', '/admin');
        $token = $crawler->filter('[data-admin-users]')->attr('data-admin-users-csrf');

        self::assertNotNull($token);

        return ['HTTP_X_CSRF_TOKEN' => $token];
    }
}
