<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
        self::assertTrue($association->joinColumns[0]->nullable);
        self::assertSame('SET NULL', $association->joinColumns[0]->onDelete);
    }
}
