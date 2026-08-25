<?php

namespace App\Tests\Unit\Entity;

use App\Entity\TrainingPlan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TrainingPlanDateValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testAcceptsConsistentActivePlanDates(): void
    {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2026-11-23'))
            ->setDurationWeeks(12);

        self::assertCount(0, $this->validator->validate($plan));
    }

    public function testActivePlanRequiresEndDate(): void
    {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setDurationWeeks(12);

        $violations = $this->validator->validate($plan);

        self::assertCount(1, $violations);
        self::assertSame('endDate', $violations[0]->getPropertyPath());
        self::assertSame(
            'La date de fin est obligatoire pour un plan actif.',
            (string) $violations[0]->getMessage(),
        );
    }

    public function testEndDateCannotPrecedeStartDate(): void
    {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-09-08'))
            ->setEndDate(new \DateTimeImmutable('2026-09-01'))
            ->setDurationWeeks(12);

        $violations = $this->validator->validate($plan);

        self::assertTrue($this->hasViolationAtPath($violations, 'endDate'));
    }

    public function testInactivePlanDoesNotRequireEndDate(): void
    {
        $plan = (new TrainingPlan())
            ->setIsActive(false)
            ->setDurationWeeks(12);

        self::assertCount(0, $this->validator->validate($plan));
    }

    public function testDurationMustBeStrictlyPositive(): void
    {
        $plan = (new TrainingPlan())
            ->setIsActive(false)
            ->setDurationWeeks(0);

        $violations = $this->validator->validate($plan);

        self::assertTrue($this->hasViolationAtPath($violations, 'durationWeeks'));
    }

    /** @param iterable<ConstraintViolationInterface> $violations */
    private function hasViolationAtPath(iterable $violations, string $path): bool
    {
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $path) {
                return true;
            }
        }

        return false;
    }
}
