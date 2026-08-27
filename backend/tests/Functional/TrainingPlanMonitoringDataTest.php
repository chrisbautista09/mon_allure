<?php

namespace App\Tests\Functional;

use App\Entity\TrainingPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrainingPlanMonitoringDataTest extends KernelTestCase
{
    public function testMonitoringFieldsAreMappedAndRequired(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(TrainingPlan::class);

        foreach ([
            'id',
            'name',
            'feasibilityIndicator',
            'progressScore',
            'isActive',
            'currentWeek',
            'durationWeeks',
            'startDate',
            'endDate',
        ] as $field) {
            self::assertTrue($metadata->hasField($field), sprintf('Le champ %s doit être disponible.', $field));
        }

        self::assertFalse($metadata->getFieldMapping('feasibilityIndicator')->nullable);
        self::assertFalse($metadata->getFieldMapping('progressScore')->nullable);
        self::assertSame(0.0, (new TrainingPlan())->getProgressScore());
    }

    public function testEveryPlanRequiresAnOwningUser(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(TrainingPlan::class);
        $association = $metadata->getAssociationMapping('user');

        self::assertFalse($association->joinColumns[0]->nullable);
        self::assertSame('CASCADE', $association->joinColumns[0]->onDelete);
    }
}
