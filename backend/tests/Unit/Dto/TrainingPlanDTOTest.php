<?php

namespace App\Tests\Unit\Dto;

use App\Dto\TrainingPlanDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TrainingPlanDTOTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
    }

    #[DataProvider('validGoalProvider')]
    public function testValidGoals(string $type, string $unit): void
    {
        $goal = $this->goal($type, $unit);

        self::assertCount(0, $this->validator->validate($goal));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validGoalProvider(): iterable
    {
        yield 'distance en kilomètres' => ['distance', 'km'];
        yield 'distance en mètres' => ['distance', 'm'];
        yield 'durée en minutes' => ['time', 'min'];
        yield 'durée en secondes' => ['time', 's'];
        yield 'épreuve en kilomètres' => ['race', 'km'];
    }

    public function testUnitMustMatchGoalType(): void
    {
        $goal = $this->goal('distance', 'min');

        $violations = $this->validator->validate($goal);

        self::assertCount(1, $violations);
        self::assertSame('targetUnit', $violations[0]->getPropertyPath());
    }

    public function testNegativeValuesAreRejected(): void
    {
        $goal = $this->goal('distance', 'km');
        $goal->targetValue = -10;
        $goal->elevationTargetDPlus = -1;

        self::assertCount(2, $this->validator->validate($goal));
    }

    public function testRaceRequiresTargetDuration(): void
    {
        $goal = $this->goal('race', 'km');
        $goal->targetDurationMinutes = null;

        $violations = $this->validator->validate($goal);

        self::assertCount(1, $violations);
        self::assertSame('targetDurationMinutes', $violations[0]->getPropertyPath());
    }

    private function goal(string $type, string $unit): TrainingPlanDTO
    {
        $goal = new TrainingPlanDTO();
        $goal->poleType = 'intermediate';
        $goal->targetType = $type;
        $goal->targetValue = 10;
        $goal->targetUnit = $unit;
        $goal->terrainType = 'road';
        $goal->elevationTargetDPlus = 100;
        $goal->targetDurationMinutes = $type === 'race' ? 45 : null;

        return $goal;
    }
}
