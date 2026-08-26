<?php

namespace App\Tests\Unit\Entity;

use App\Entity\IntensityZone;
use App\Entity\Session;
use App\Entity\SessionIntensityZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class IntensityZoneModelTest extends KernelTestCase
{
    public function testSessionSupportsSeveralIntensityZones(): void
    {
        $session = new Session();
        $z1 = $this->distribution('Z1', 60.0);
        $z2 = $this->distribution('Z2', 40.0);

        $session->addSessionIntensityZone($z1);
        $session->addSessionIntensityZone($z2);

        self::assertCount(2, $session->getSessionIntensityZones());
        self::assertSame($session, $z1->getSession());
        self::assertSame($session, $z2->getSession());
    }

    public function testDoctrineMappingMatchesTheAssociationEntityFromTheDataModel(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(SessionIntensityZone::class);

        foreach (['session' => Session::class, 'intensityZone' => IntensityZone::class] as $field => $target) {
            $association = $metadata->getAssociationMapping($field);
            self::assertSame(ClassMetadata::MANY_TO_ONE, $association->type());
            self::assertSame($target, $association->targetEntity);
            self::assertFalse($association->joinColumns[0]->nullable);
            self::assertSame('CASCADE', $association->joinColumns[0]->onDelete);
        }

        self::assertArrayHasKey('unique_session_intensity_zone', $metadata->table['uniqueConstraints']);
    }

    public function testValidZoneAndDistributionAreAccepted(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $distribution = $this->distribution('Z2', 75.0)->setSession(new Session());

        self::assertCount(0, $validator->validate($distribution->getIntensityZone()));
        self::assertCount(0, $validator->validate($distribution));
    }

    public function testIncoherentZoneBoundsAreRejected(): void
    {
        self::bootKernel();
        $zone = (new IntensityZone())
            ->setName('Z6')
            ->setVmaCoefMin(0.9)
            ->setVmaCoefMax(0.8)
            ->setFcmPercentMin(95.0)
            ->setFcmPercentMax(80.0);
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($zone);
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('name', $paths);
        self::assertContains('vmaCoefMin', $paths);
        self::assertContains('fcmPercentMin', $paths);
    }

    public function testDistributionRequiresBothRelationsAndAUsablePercentage(): void
    {
        self::bootKernel();
        $distribution = (new SessionIntensityZone())->setDurationPercent(120.0);
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($distribution);
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('durationPercent', $paths);
        self::assertContains('intensityZone', $paths);
        self::assertContains('session', $paths);
    }

    private function distribution(string $name, float $durationPercent): SessionIntensityZone
    {
        $zone = (new IntensityZone())
            ->setName($name)
            ->setVmaCoefMin(0.6)
            ->setVmaCoefMax(0.7)
            ->setFcmPercentMin(60.0)
            ->setFcmPercentMax(70.0);

        return (new SessionIntensityZone())
            ->setIntensityZone($zone)
            ->setDurationPercent($durationPercent);
    }
}
