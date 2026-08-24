<?php

namespace App\Tests\Unit\Form;

use App\Entity\Performance;
use App\Form\PerformanceType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class PerformanceTypeTest extends TypeTestCase
{
    /** @return list<FormExtensionInterface> */
    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [new ValidatorExtension($validator)];
    }

    public function testValidPerformanceIsMappedAndNormalized(): void
    {
        $performance = new Performance();
        $form = $this->factory->create(PerformanceType::class, $performance);

        $form->submit([
            'durationSec' => '3600',
            'distanceKm' => '10.25',
            'elevationDPlus' => '180',
            'terrainType' => 'trail',
            'avgHr' => '158',
            'comment' => 'Séance régulière et maîtrisée.',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(3600, $performance->getDurationSec());
        self::assertSame(10.25, $performance->getDistanceKm());
        self::assertSame(180, $performance->getElevationDPlus());
        self::assertSame(158, $performance->getAvgHr());
        self::assertSame('Séance régulière et maîtrisée.', $performance->getComment());
        self::assertSame('trail', $form->get('terrainType')->getData());
        self::assertTrue($form->isValid());
    }

    public function testTerrainIsRequiredAndRestrictedToKnownValues(): void
    {
        $form = $this->factory->create(PerformanceType::class, new Performance());
        $form->submit([
            'durationSec' => '3600',
            'distanceKm' => '10',
            'elevationDPlus' => '0',
            'terrainType' => 'track',
            'avgHr' => '',
            'comment' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('terrainType')->getErrors(true)->count());
    }

    public function testInvalidValuesAreRejectedByServerValidation(): void
    {
        $performance = (new Performance())
            ->setDurationSec(0)
            ->setDistanceKm(-1)
            ->setElevationDPlus(-20)
            ->setAvgHr(250);
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($performance);

        self::assertCount(4, $violations);
    }
}
