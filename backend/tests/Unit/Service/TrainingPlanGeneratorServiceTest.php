<?php

namespace App\Tests\Unit\Service;

use App\Dto\TrainingPlanDTO;
use App\Entity\AlgorithmParameter;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\AlgorithmParameterRepository;
use App\Service\AlgorithmParameterService;
use App\Service\FeasibilityService;
use App\Service\SessionGeneratorService;
use App\Service\TrainingPlanGeneratorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TrainingPlanGeneratorServiceTest extends TestCase
{
    public function testGeneratesCompletePlanFromProfileGoalAndParameters(): void
    {
        $service = $this->serviceWithParameters(8, 18, 1);
        $user = $this->userWithProfile(16.5);
        $goal = $this->distanceGoal(42.2, 'km');

        $plan = $service->generatePlan($user, $goal);

        self::assertSame($user, $plan->getUser());
        self::assertTrue($user->getTrainingPlans()->contains($plan));
        self::assertSame('Objectif 42.2 km', $plan->getName());
        self::assertSame('performance', $plan->getPoleType());
        self::assertSame('distance', $plan->getTargetType());
        self::assertSame(42.2, $plan->getTargetValue());
        self::assertSame('km', $plan->getTargetUnit());
        self::assertSame('trail', $plan->getTerrainType());
        self::assertSame(900, $plan->getElevationTargetDPlus());
        self::assertSame('OPTIMAL', $plan->getFeasibilityIndicator());
        self::assertSame(16, $plan->getDurationWeeks());
        self::assertSame(1, $plan->getCurrentWeek());
        self::assertSame(0.0, $plan->getProgressScore());
        self::assertTrue($plan->isActive());
        self::assertNotNull($plan->getStartDate());
        self::assertNotNull($plan->getEndDate());
        self::assertGreaterThan($plan->getStartDate(), $plan->getEndDate());
    }

    public function testDurationIsClampedByAlgorithmParameters(): void
    {
        $service = $this->serviceWithParameters(10, 12, 2);

        $shortPlan = $service->generatePlan(
            $this->userWithProfile(13.5),
            $this->distanceGoal(5, 'km')
        );
        $longPlan = $service->generatePlan(
            $this->userWithProfile(13.5),
            $this->distanceGoal(100, 'km')
        );

        self::assertSame(10, $shortPlan->getDurationWeeks());
        self::assertSame(12, $longPlan->getDurationWeeks());
    }

    public function testGeneratesRaceGoalWithDistanceAndTargetTime(): void
    {
        $service = $this->serviceWithParameters(8, 18, 1);
        $goal = $this->distanceGoal(10, 'km');
        $goal->targetType = 'race';
        $goal->targetDurationMinutes = 45;

        $plan = $service->generatePlan($this->userWithProfile(16), $goal);

        self::assertSame('Épreuve 10 km en 45 min', $plan->getName());
        self::assertSame('race', $plan->getTargetType());
        self::assertSame(45, $plan->getTargetDurationMinutes());
        self::assertSame(8, $plan->getDurationWeeks());
    }

    public function testProfileIsRequired(): void
    {
        $service = $this->serviceWithParameters(8, 18, 0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('profil physiologique');

        $service->generatePlan(new User(), $this->distanceGoal(10, 'km'));
    }

    public function testAlgorithmParametersAreRequired(): void
    {
        $service = $this->serviceWithParameterEntities([], 0);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('default_plan_min_weeks');

        $service->generatePlan($this->userWithProfile(14), $this->distanceGoal(10, 'km'));
    }

    private function serviceWithParameters(
        int $minimumWeeks,
        int $maximumWeeks,
        int $expectedSessionGenerations,
    ): TrainingPlanGeneratorService
    {
        return $this->serviceWithParameterEntities([
            $this->parameter('default_plan_min_weeks', $minimumWeeks),
            $this->parameter('default_plan_max_weeks', $maximumWeeks),
            $this->parameter('max_sessions_performance', 5),
        ], $expectedSessionGenerations);
    }

    /** @param list<AlgorithmParameter> $parameters */
    private function serviceWithParameterEntities(
        array $parameters,
        int $expectedSessionGenerations,
    ): TrainingPlanGeneratorService
    {
        $repository = $this->createStub(AlgorithmParameterRepository::class);
        $repository->method('findCurrent')->willReturn(array_combine(
            array_map(static fn (AlgorithmParameter $parameter): string => (string) $parameter->getParameterKey(), $parameters),
            $parameters,
        ));

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $sessionGenerator = $this->createMock(SessionGeneratorService::class);
        $sessionGenerator
            ->expects(self::exactly($expectedSessionGenerations))
            ->method('generate')
            ->willReturn([]);

        return new TrainingPlanGeneratorService(
            new AlgorithmParameterService($repository),
            $validator,
            new FeasibilityService(new AlgorithmParameterService($repository)),
            $sessionGenerator,
        );
    }

    private function parameter(string $key, float $value): AlgorithmParameter
    {
        return (new AlgorithmParameter())
            ->setParameterKey($key)
            ->setParameterValue($value);
    }

    private function userWithProfile(float $vma): User
    {
        $user = new User();
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma($vma)
            ->setUser($user);
        $user->setProfile($profile);

        return $user;
    }

    private function distanceGoal(float $value, string $unit): TrainingPlanDTO
    {
        $goal = new TrainingPlanDTO();
        $goal->poleType = 'performance';
        $goal->targetType = 'distance';
        $goal->targetValue = $value;
        $goal->targetUnit = $unit;
        $goal->terrainType = 'trail';
        $goal->elevationTargetDPlus = 900;

        return $goal;
    }
}
