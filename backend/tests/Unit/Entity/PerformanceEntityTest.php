<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PerformanceEntityTest extends KernelTestCase
{
    public function testPerformanceExposesTheActualTrainingResults(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-26 09:00:00');
        $user = new User();
        $performance = (new Performance())
            ->setDistanceKm(10.2)
            ->setDurationSec(3540)
            ->setElevationDPlus(80)
            ->setAvgHr(154)
            ->setComment('Séance fluide.')
            ->setCreatedAt($createdAt)
            ->setUser($user);

        self::assertSame(10.2, $performance->getDistanceKm());
        self::assertSame(3540, $performance->getDurationSec());
        self::assertSame(80, $performance->getElevationDPlus());
        self::assertSame(154, $performance->getAvgHr());
        self::assertSame('Séance fluide.', $performance->getComment());
        self::assertSame($createdAt, $performance->getCreatedAt());
        self::assertSame($user, $performance->getUser());
    }

    public function testSessionKeepsTheOneToOneAssociationSynchronized(): void
    {
        $session = new Session();
        $performance = new Performance();

        $session->setPerformance($performance);

        self::assertSame($performance, $session->getPerformance());
        self::assertSame($session, $performance->getSession());

        $session->clearPerformance();

        self::assertNull($session->getPerformance());
        self::assertNull($performance->getSession());
    }

    public function testDoctrineMappingAllowsOnlyOnePerformancePerSession(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(Performance::class);
        $association = $metadata->getAssociationMapping('session');

        self::assertSame(ClassMetadata::ONE_TO_ONE, $association->type());
        self::assertSame(Session::class, $association->targetEntity);
        self::assertSame('performance', $association->inversedBy);
        self::assertTrue($association->joinColumns[0]->unique);
        self::assertFalse($association->joinColumns[0]->nullable);
        self::assertSame('CASCADE', $association->joinColumns[0]->onDelete);
    }

    public function testTerrainIsDerivedFromTheSessionsTrainingPlan(): void
    {
        $plan = (new TrainingPlan())->setTerrainType('trail');
        $session = new Session();
        $plan->addSession($session);
        $performance = (new Performance())->setSession($session);

        self::assertSame('trail', $performance->getTerrainType());
    }

    public function testValidationRequiresCompleteAndConsistentGraphData(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $owner = new User();
        $otherUser = new User();
        $plan = (new TrainingPlan())->setUser($owner);
        $session = (new Session())->setTrainingPlan($plan);
        $performance = (new Performance())
            ->setDistanceKm(10)
            ->setDurationSec(3600)
            ->setSession($session)
            ->setUser($otherUser);

        $violations = $validator->validate($performance);

        self::assertGreaterThanOrEqual(1, $violations->count());
        self::assertSame('user', $violations[0]->getPropertyPath());
        self::assertSame(
            'La performance doit appartenir au propriétaire du plan d’entraînement.',
            $violations[0]->getMessage(),
        );
    }

    public function testValidationRejectsPerformanceWithoutSessionOrUser(): void
    {
        self::bootKernel();
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate(
            (new Performance())->setDistanceKm(10)->setDurationSec(3600),
        );
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('session', $paths);
        self::assertContains('user', $paths);
    }
}
