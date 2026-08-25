<?php

namespace App\Tests\Functional;

use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SessionRepositoryTest extends KernelTestCase
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

    public function testFindsOnlySessionsFromRequestedRecentWeeksInChronologicalOrder(): void
    {
        $plan = $this->persistPlanWithSessions();
        $repository = self::getContainer()->get(SessionRepository::class);

        $sessions = $repository->findRecentForPlan(
            $plan,
            new \DateTimeImmutable('2026-09-21'),
            3,
        );

        self::assertCount(3, $sessions);
        self::assertSame(
            ['2026-09-01', '2026-09-08', '2026-09-15'],
            array_map(static fn (Session $session): string => $session->getDate()?->format('Y-m-d'), $sessions),
        );
        self::assertSame(
            ['done', 'partially_done', 'missed'],
            array_map(static fn (Session $session): string => $session->getStatus(), $sessions),
        );
    }

    public function testNumberOfWeeksMustBePositive(): void
    {
        $plan = $this->persistPlanWithSessions();
        $repository = self::getContainer()->get(SessionRepository::class);

        $this->expectException(\InvalidArgumentException::class);
        $repository->findRecentForPlan($plan, new \DateTimeImmutable(), 0);
    }

    private function persistPlanWithSessions(): TrainingPlan
    {
        $user = (new User())
            ->setEmail('recent-sessions@example.com')
            ->setPseudo('recent-session-runner')
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan récent')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-17'))
            ->setEndDate(new \DateTimeImmutable('2026-10-11'))
            ->setDurationWeeks(8);
        $sessionData = [
            ['2026-08-25', 'done'],
            ['2026-09-01', 'done'],
            ['2026-09-08', 'partially_done'],
            ['2026-09-15', 'missed'],
            ['2026-09-22', 'planned'],
        ];

        foreach ($sessionData as $index => [$date, $status]) {
            $plan->addSession((new Session())
                ->setWeekIndex($index + 1)
                ->setDayOfWeek(2)
                ->setTitle('Séance '.($index + 1))
                ->setSessionType('endurance')
                ->setDate(new \DateTimeImmutable($date))
                ->setStatus($status));
        }

        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $plan;
    }
}
