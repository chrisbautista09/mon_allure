<?php

namespace App\Tests\Functional;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Service\HistoryService;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class HistoryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testReturnsFormattedPaginatedHistoryWithOptionalPerformance(): void
    {
        $user = $this->persistHistory();
        $historyService = $this->historyService();

        $firstPage = $historyService->getSessionHistory($user, 1, 1);

        self::assertSame([
            'page' => 1,
            'perPage' => 1,
            'totalItems' => 2,
            'totalPages' => 2,
        ], $firstPage['pagination']);
        self::assertCount(1, $firstPage['items']);
        $completed = $firstPage['items'][0];
        self::assertSame('Footing endurance', $completed['title']);
        self::assertSame('COMPLETED', $completed['status']);
        self::assertSame('Rester en aisance respiratoire.', $completed['instructions']);
        self::assertSame('Plan historique', $completed['plan']['name']);
        self::assertSame(8.2, $completed['performance']['distanceKm']);
        self::assertSame(3000, $completed['performance']['durationSec']);
        self::assertSame(90, $completed['performance']['elevationDPlus']);
        self::assertSame('road', $completed['performance']['terrainType']);
        self::assertSame(148, $completed['performance']['avgHr']);
        self::assertSame('Bonnes sensations.', $completed['performance']['comment']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $completed['performance']['recordedAt']);

        $secondPage = $historyService->getSessionHistory($user, 2, 1);
        self::assertSame('MISSED', $secondPage['items'][0]['status']);
        self::assertNull($secondPage['items'][0]['performance']);
    }

    public function testReturnsExplicitEmptyPaginatedHistory(): void
    {
        $user = $this->persistUser('empty-history@example.com');

        $history = $this->historyService()->getSessionHistory($user);

        self::assertSame([], $history['items']);
        self::assertSame([
            'page' => 1,
            'perPage' => 20,
            'totalItems' => 0,
            'totalPages' => 0,
        ], $history['pagination']);
    }

    private function persistHistory(): User
    {
        $user = $this->persistUser('history-service@example.com', false);
        $plan = $this->plan();
        $missed = $this->session('Séance manquée', new \DateTimeImmutable('-2 days'), 'missed');
        $completed = $this->session('Footing endurance', new \DateTimeImmutable('-1 day'), 'completed')
            ->setInstructions('Rester en aisance respiratoire.');
        $performance = (new Performance())
            ->setDistanceKm(8.2)
            ->setDurationSec(3000)
            ->setElevationDPlus(90)
            ->setAvgHr(148)
            ->setComment('Bonnes sensations.')
            ->setUser($user);
        $completed->setPerformance($performance);
        $plan->addSession($missed)->addSession($completed);
        $user->addTrainingPlan($plan)->addPerformance($performance);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function persistUser(string $email, bool $flush = true): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $this->entityManager->persist($user);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $user;
    }

    private function plan(): TrainingPlan
    {
        return (new TrainingPlan())
            ->setName('Plan historique')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('-4 weeks'))
            ->setEndDate(new \DateTimeImmutable('+4 weeks'))
            ->setDurationWeeks(8);
    }

    private function session(string $title, \DateTimeImmutable $date, string $status): Session
    {
        return (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(1)
            ->setTitle($title)
            ->setSessionType('endurance')
            ->setDate($date)
            ->setStatus($status);
    }

    private function historyService(): HistoryService
    {
        return new HistoryService(
            self::getContainer()->get(SessionRepository::class),
            new MockClock('today'),
        );
    }
}
