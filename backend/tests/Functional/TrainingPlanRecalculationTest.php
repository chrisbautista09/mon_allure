<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\AdaptationDecision;
use App\Service\AdaptationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrainingPlanRecalculationTest extends KernelTestCase
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

    public function testNineSuccessfulSessionsIncreaseLoadAndRegenerateFuture(): void
    {
        [$performance, $pastSession, $futureSession] = $this->scenario(9, 3);
        $pastId = $pastSession->getId();
        $futureId = $futureSession->getId();

        $result = self::getContainer()->get(AdaptationService::class)->adapt($performance);
        $this->entityManager->persist($performance);
        $this->entityManager->flush();

        self::assertSame(90.0, $result->successRate);
        self::assertSame(AdaptationDecision::INCREASE, $result->decision);
        self::assertSame(1.1, $result->loadFactor);
        self::assertSame(1, $result->adjustedSessionsCount);
        self::assertNotNull($this->entityManager->find(Session::class, $pastId));
        self::assertNull($this->entityManager->find(Session::class, $futureId));
        self::assertSame(44, $this->replacementSession($performance)->getPlannedDurationMin());
    }

    public function testMediumSuccessRateMaintainsExistingFutureSession(): void
    {
        [$performance, , $futureSession] = $this->scenario(7, 6);
        $futureId = $futureSession->getId();

        $result = self::getContainer()->get(AdaptationService::class)->adapt($performance);
        $this->entityManager->persist($performance);
        $this->entityManager->flush();

        self::assertSame(70.0, $result->successRate);
        self::assertSame(AdaptationDecision::MAINTAIN, $result->decision);
        self::assertSame(1.0, $result->loadFactor);
        self::assertSame(0, $result->adjustedSessionsCount);
        self::assertNotNull($this->entityManager->find(Session::class, $futureId));
        self::assertSame(40, $futureSession->getPlannedDurationMin());
    }

    public function testFiveSuccessfulSessionsReduceLoadAndPreservePast(): void
    {
        [$performance, $pastSession, $futureSession] = $this->scenario(5, 3);
        $pastId = $pastSession->getId();
        $futureId = $futureSession->getId();

        $result = self::getContainer()->get(AdaptationService::class)->adapt($performance);
        $this->entityManager->persist($performance);
        $this->entityManager->flush();

        self::assertSame(50.0, $result->successRate);
        self::assertSame(AdaptationDecision::REDUCE, $result->decision);
        self::assertSame(0.9, $result->loadFactor);
        self::assertSame(1, $result->adjustedSessionsCount);
        self::assertNotNull($this->entityManager->find(Session::class, $pastId));
        self::assertNull($this->entityManager->find(Session::class, $futureId));
        self::assertSame(36, $this->replacementSession($performance)->getPlannedDurationMin());
    }

    /** @return array{Performance, Session, Session} */
    private function scenario(int $successfulSessions, int $currentWeek): array
    {
        $user = (new User())
            ->setEmail(sprintf('recalculation-%d-%d@example.com', $successfulSessions, $currentWeek))
            ->setPseudo(sprintf('recalculation-%d-%d', $successfulSessions, $currentWeek))
            ->setPassword('test-password');
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190);
        $user->setProfile($profile);
        $plan = (new TrainingPlan())
            ->setName('Plan test recalcul')
            ->setPoleType('performance')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-17'))
            ->setEndDate(new \DateTimeImmutable('2026-10-11'))
            ->setDurationWeeks(8);
        $user->addTrainingPlan($plan);
        $firstWeek = $currentWeek - 2;
        $pastSession = null;
        $currentSession = null;

        for ($index = 0; $index < 10; ++$index) {
            $isCurrent = $index === 9;
            $successful = $index < $successfulSessions - 1 || $isCurrent;
            $session = $this->session(
                $firstWeek + min(2, intdiv($index, 3)),
                (new \DateTimeImmutable('2026-08-17'))->modify(sprintf('+%d days', $index)),
                $isCurrent ? 'completed' : ($successful ? 'completed' : 'missed'),
            );
            $plan->addSession($session);
            $pastSession ??= $session;

            if ($isCurrent) {
                $currentSession = $session;
            }
        }

        $futureSession = $this->session(
            $currentWeek + 1,
            new \DateTimeImmutable('2026-09-08'),
            'planned',
        );
        $plan->addSession($futureSession);
        $this->persistConfiguration();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $performance = (new Performance())
            ->setUser($user)
            ->setSession($currentSession)
            ->setDistanceKm(10)
            ->setDurationSec(3600)
            ->setAvgHr(130);

        return [$performance, $pastSession, $futureSession];
    }

    private function session(int $week, \DateTimeImmutable $date, string $status): Session
    {
        return (new Session())
            ->setWeekIndex($week)
            ->setDayOfWeek(2)
            ->setTitle('Séance adaptable')
            ->setDescription('Consignes adaptées.')
            ->setSessionType('threshold')
            ->setPlannedDistanceKm(8)
            ->setPlannedDurationMin(40)
            ->setPlannedElevationDPlus(100)
            ->setPlannedVmaCoef(0.9)
            ->setPlannedFcmZone('Z2')
            ->setDate($date)
            ->setStatus($status);
    }

    private function persistConfiguration(): void
    {
        $this->entityManager->persist((new IntensityZone())
            ->setName('Z2')
            ->setVmaCoefMin(0.65)
            ->setVmaCoefMax(0.75)
            ->setFcmPercentMin(60)
            ->setFcmPercentMax(70));

        foreach ([
            'success_validation_rate' => 80,
            'progression_max_percent' => 10,
            'recovery_week_frequency' => 4,
        ] as $key => $value) {
            $this->entityManager->persist((new AlgorithmParameter())
                ->setParameterKey($key)
                ->setParameterValue($value));
        }
    }

    private function replacementSession(Performance $performance): Session
    {
        $plan = $performance->getSession()?->getTrainingPlan();
        $replacement = $this->entityManager->getRepository(Session::class)->findOneBy([
            'trainingPlan' => $plan,
            'date' => new \DateTimeImmutable('2026-09-08'),
        ]);

        self::assertInstanceOf(Session::class, $replacement);

        return $replacement;
    }
}
