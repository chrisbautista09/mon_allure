<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Session;
use App\Entity\TrainingPlan;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SessionEntityTest extends KernelTestCase
{
    public function testSessionExposesItsTrainingData(): void
    {
        $date = new \DateTimeImmutable('2026-09-07');
        $session = (new Session())
            ->setWeekIndex(2)
            ->setDayOfWeek(1)
            ->setTitle('  Endurance fondamentale  ')
            ->setInstructions('  Course en aisance respiratoire.  ')
            ->setSessionType('endurance')
            ->setPlannedDistanceKm(8.5)
            ->setPlannedDurationMin(50)
            ->setPlannedElevationDPlus(120)
            ->setPlannedVmaCoef(0.7)
            ->setPlannedFcmZone('Z2')
            ->setDate($date);

        self::assertSame(2, $session->getWeekIndex());
        self::assertSame(1, $session->getDayOfWeek());
        self::assertSame('Endurance fondamentale', $session->getTitle());
        self::assertSame('Course en aisance respiratoire.', $session->getDescription());
        self::assertSame('Course en aisance respiratoire.', $session->getInstructions());
        self::assertSame('endurance', $session->getSessionType());
        self::assertSame(8.5, $session->getPlannedDistanceKm());
        self::assertSame(50, $session->getPlannedDurationMin());
        self::assertSame(120, $session->getPlannedElevationDPlus());
        self::assertSame(0.7, $session->getPlannedVmaCoef());
        self::assertSame('Z2', $session->getPlannedFcmZone());
        self::assertSame($date, $session->getDate());
        self::assertSame('planned', $session->getStatus());
    }

    public function testTrainingPlanKeepsTheAssociationSynchronized(): void
    {
        $plan = new TrainingPlan();
        $session = new Session();

        $plan->addSession($session);

        self::assertTrue($plan->getSessions()->contains($session));
        self::assertSame($plan, $session->getTrainingPlan());

        $plan->removeSession($session);

        self::assertFalse($plan->getSessions()->contains($session));
        self::assertNull($session->getTrainingPlan());
    }

    public function testDoctrineMappingRequiresAPlanAndDeletesSessionsWithIt(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(Session::class);
        $association = $metadata->getAssociationMapping('trainingPlan');

        self::assertSame(ClassMetadata::MANY_TO_ONE, $association->type());
        self::assertSame(TrainingPlan::class, $association->targetEntity);
        self::assertSame('sessions', $association->inversedBy);
        self::assertFalse($association->joinColumns[0]->nullable);
        self::assertSame('CASCADE', $association->joinColumns[0]->onDelete);
        self::assertSame('instructions', $metadata->getFieldMapping('instructions')->columnName);
        self::assertFalse($metadata->getFieldMapping('date')->nullable);
    }

    public function testValidationRequiresTitleDateAndTrainingPlan(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $violations = $validator->validate(new Session());
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('title', $paths);
        self::assertContains('date', $paths);
        self::assertContains('trainingPlan', $paths);
    }
}
