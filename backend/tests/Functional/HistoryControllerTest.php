<?php

namespace App\Tests\Functional;

use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HistoryControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessSessionHistory(): void
    {
        $this->client->request('GET', '/api/history/sessions');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAuthenticatedUserReceivesOnlyOwnPastSessionsAsJson(): void
    {
        $owner = $this->persistUserWithSessions('history-api-owner@example.com', [
            ['-2 days', 'completed'],
            ['-1 day', 'missed'],
            ['+1 day', 'planned'],
        ]);
        $this->persistUserWithSessions('history-api-other@example.com', [
            ['-1 day', 'completed'],
        ]);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/api/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = $this->responseData();
        self::assertSame(2, $response['pagination']['totalItems']);
        self::assertCount(2, $response['items']);
        self::assertSame('MISSED', $response['items'][0]['status']);
        self::assertSame('COMPLETED', $response['items'][1]['status']);
        self::assertSame('Plan history-api-owner@example.com', $response['items'][0]['plan']['name']);
    }

    public function testSessionHistoryEndpointIsPaginable(): void
    {
        $user = $this->persistUserWithSessions('history-api-pages@example.com', [
            ['-3 days', 'completed'],
            ['-2 days', 'completed'],
            ['-1 day', 'completed'],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/history/sessions?page=2&perPage=1');

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(2, $response['pagination']['page']);
        self::assertSame(1, $response['pagination']['perPage']);
        self::assertSame(3, $response['pagination']['totalItems']);
        self::assertSame(3, $response['pagination']['totalPages']);
        self::assertCount(1, $response['items']);
        self::assertSame((new \DateTimeImmutable('-2 days'))->format('Y-m-d'), $response['items'][0]['date']);
    }

    public function testSessionHistoryEndpointRejectsInvalidPagination(): void
    {
        $this->client->loginUser($this->persistUserWithSessions('history-api-invalid@example.com', []));

        $this->client->request('GET', '/api/history/sessions?page=0&perPage=101');

        self::assertResponseStatusCodeSame(422);
        $response = $this->responseData();
        self::assertSame('Les paramètres de pagination sont invalides.', $response['message']);
        self::assertArrayHasKey('page', $response['constraints']);
        self::assertArrayHasKey('perPage', $response['constraints']);
    }

    /** @param list<array{string, string}> $sessionData */
    private function persistUserWithSessions(string $email, array $sessionData): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan '.$email)
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('-4 weeks'))
            ->setEndDate(new \DateTimeImmutable('+4 weeks'))
            ->setDurationWeeks(8);

        foreach ($sessionData as $index => [$date, $status]) {
            $plan->addSession((new Session())
                ->setWeekIndex(1)
                ->setDayOfWeek(($index % 7) + 1)
                ->setTitle('Séance '.($index + 1))
                ->setInstructions('Consignes de la séance.')
                ->setSessionType('endurance')
                ->setDate(new \DateTimeImmutable($date))
                ->setStatus($status));
        }

        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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
