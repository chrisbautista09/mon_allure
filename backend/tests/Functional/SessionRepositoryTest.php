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
            ['completed', 'completed', 'missed'],
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

    public function testFindsOnlyOwnedPastSessionsInReverseChronologicalOrder(): void
    {
        $owner = $this->persistUserWithSessions('history-owner@example.com', [
            '2026-08-20',
            '2026-08-26',
            '2026-08-27',
        ]);
        $this->persistUserWithSessions('history-other@example.com', ['2026-08-25']);
        $repository = self::getContainer()->get(SessionRepository::class);

        $sessions = iterator_to_array($repository->findPastSessionsByUser(
            $owner,
            today: new \DateTimeImmutable('2026-08-26'),
        ));

        self::assertSame(
            ['2026-08-26', '2026-08-20'],
            array_map(static fn (Session $session): string => $session->getDate()?->format('Y-m-d'), $sessions),
        );
        self::assertSame(
            ['history-owner@example.com', 'history-owner@example.com'],
            array_map(
                static fn (Session $session): ?string => $session->getTrainingPlan()?->getUser()?->getEmail(),
                $sessions,
            ),
        );
    }

    public function testPastSessionsArePaginated(): void
    {
        $user = $this->persistUserWithSessions('history-pages@example.com', [
            '2026-08-20',
            '2026-08-21',
            '2026-08-22',
            '2026-08-23',
            '2026-08-24',
        ]);
        $repository = self::getContainer()->get(SessionRepository::class);
        $today = new \DateTimeImmutable('2026-08-26');
        $firstPage = $repository->findPastSessionsByUser($user, 1, 2, $today);
        $secondPage = $repository->findPastSessionsByUser($user, 2, 2, $today);

        self::assertCount(5, $firstPage);
        self::assertSame(
            ['2026-08-24', '2026-08-23'],
            array_map(
                static fn (Session $session): string => $session->getDate()?->format('Y-m-d'),
                iterator_to_array($firstPage),
            ),
        );
        self::assertSame(
            ['2026-08-22', '2026-08-21'],
            array_map(
                static fn (Session $session): string => $session->getDate()?->format('Y-m-d'),
                iterator_to_array($secondPage),
            ),
        );
    }

    public function testPastSessionsPaginationRejectsInvalidValues(): void
    {
        $repository = self::getContainer()->get(SessionRepository::class);
        $user = $this->persistUserWithSessions('history-invalid-page@example.com', []);

        $this->expectException(\InvalidArgumentException::class);
        $repository->findPastSessionsByUser($user, 0);
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
            ['2026-08-25', 'completed'],
            ['2026-09-01', 'completed'],
            ['2026-09-08', 'completed'],
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

    /** @param list<string> $dates */
    private function persistUserWithSessions(string $email, array $dates): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Historique')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-01'))
            ->setDurationWeeks(8);

        foreach ($dates as $index => $date) {
            $plan->addSession((new Session())
                ->setWeekIndex(1)
                ->setDayOfWeek(($index % 7) + 1)
                ->setTitle('Séance historique '.($index + 1))
                ->setSessionType('endurance')
                ->setDate(new \DateTimeImmutable($date)));
        }

        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
