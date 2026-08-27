<?php

namespace App\Tests\Functional;

use App\DataFixtures\IntensityZoneFixtures;
use App\Entity\IntensityZone;
use App\Repository\IntensityZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IntensityZoneFixturesTest extends KernelTestCase
{
    public function testFixtureLoadsEveryZoneAndCanBeRunTwice(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $repository = self::getContainer()->get(IntensityZoneRepository::class);
        $fixture = new IntensityZoneFixtures($repository);

        $fixture->load($entityManager);
        $fixture->load($entityManager);

        self::assertSame(5, $repository->count([]));
        foreach (IntensityZoneFixtures::DEFAULTS as $name => $values) {
            $zone = $repository->findOneBy(['name' => $name]);
            self::assertInstanceOf(IntensityZone::class, $zone);
            self::assertSame($values['vmaMin'], $zone->getVmaCoefMin());
            self::assertSame($values['vmaMax'], $zone->getVmaCoefMax());
            self::assertSame($values['fcmMin'], $zone->getFcmPercentMin());
            self::assertSame($values['fcmMax'], $zone->getFcmPercentMax());
        }
    }
}
