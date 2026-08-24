<?php

namespace App\Tests\Unit\Service;

use App\Entity\IntensityZone;
use App\Entity\Profile;
use App\Service\PaceCalculatorService;
use PHPUnit\Framework\TestCase;

final class PaceCalculatorServiceTest extends TestCase
{
    private PaceCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new PaceCalculatorService();
    }

    public function testCalculatesPersonalizedPaceSpeedAndHeartRateZone(): void
    {
        $result = $this->service->calculate($this->profile(), $this->zone());

        self::assertSame(12.6, $result->speed);
        self::assertSame(286, $result->paceSecondsPerKm);
        self::assertSame('4:46/km', $result->pace);
        self::assertSame(70.0, $result->vmaPercent);
        self::assertSame('Z2', $result->heartRateZone);
        self::assertSame(133, $result->heartRateMin);
        self::assertSame(152, $result->heartRateMax);
        self::assertSame(70.0, $result->fcmPercentMin);
        self::assertSame(80.0, $result->fcmPercentMax);
    }

    public function testAcceptsCoefficientInsideSelectedZone(): void
    {
        $result = $this->service->calculate($this->profile(), $this->zone(), 0.75);

        self::assertSame(13.5, $result->speed);
        self::assertSame('4:27/km', $result->pace);
        self::assertSame(75.0, $result->vmaPercent);
    }

    public function testRejectsCoefficientOutsideSelectedZone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('compris entre 0.65 et 0.75');

        $this->service->calculate($this->profile(), $this->zone(), 0.80);
    }

    public function testVmaIsRequired(): void
    {
        $profile = $this->profile()->setVma(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('VMA');

        $this->service->calculate($profile, $this->zone());
    }

    public function testFcmIsRequired(): void
    {
        $profile = $this->profile()->setFcm(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('FCM');

        $this->service->calculate($profile, $this->zone());
    }

    public function testRejectsIncoherentIntensityZone(): void
    {
        $zone = $this->zone()->setVmaCoefMin(0.80)->setVmaCoefMax(0.70);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('incomplète ou incohérente');

        $this->service->calculate($this->profile(), $zone);
    }

    public function testResultCanBeSerialized(): void
    {
        $result = $this->service->calculate($this->profile(), $this->zone());

        self::assertSame('4:46/km', $result->toArray()['pace']);
        self::assertSame('Z2', $result->toArray()['heartRateZone']);
    }

    private function profile(): Profile
    {
        return (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(18)
            ->setFcm(190);
    }

    private function zone(): IntensityZone
    {
        return (new IntensityZone())
            ->setName('Z2')
            ->setVmaCoefMin(0.65)
            ->setVmaCoefMax(0.75)
            ->setFcmPercentMin(70)
            ->setFcmPercentMax(80);
    }
}
