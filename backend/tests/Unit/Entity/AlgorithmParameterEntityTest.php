<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AlgorithmParameter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AlgorithmParameterEntityTest extends TestCase
{
    public function testSupportedKeysMatchTheFourteenMcdParameters(): void
    {
        self::assertSame([
            'progression_max_percent',
            'recovery_week_frequency',
            'max_sessions_discovery',
            'max_sessions_intermediate',
            'max_sessions_performance',
            'long_run_ratio_max',
            'coef_endurance',
            'coef_active',
            'coef_threshold',
            'coef_vma',
            'default_plan_min_weeks',
            'default_plan_max_weeks',
            'missed_session_tolerance',
            'success_validation_rate',
        ], AlgorithmParameter::SUPPORTED_KEYS);
    }

    public function testNumericValueAndAuditFieldsAreCoherent(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('  COEF_ENDURANCE  ')
            ->setParameterValue(0.7)
            ->setDescription('  Coefficient endurance  ');

        self::assertSame('coef_endurance', $parameter->getParameterKey());
        self::assertSame(0.7, $parameter->getParameterValue());
        self::assertSame('Coefficient endurance', $parameter->getDescription());
        self::assertInstanceOf(\DateTimeImmutable::class, $parameter->getUpdatedAt());
    }

    public function testUpdateValueRefreshesModificationDate(): void
    {
        $initialDate = new \DateTimeImmutable('2026-01-01');
        $parameter = (new AlgorithmParameter())
            ->setParameterValue(2)
            ->setUpdatedAt($initialDate);

        $parameter->updateValue(3);

        self::assertSame(3.0, $parameter->getParameterValue());
        self::assertGreaterThan($initialDate, $parameter->getUpdatedAt());
    }

    public function testSymfonyConstraintRejectsAnOutOfBoundsValueWithExplicitMessage(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey(AlgorithmParameter::KEY_SUCCESS_VALIDATION_RATE)
            ->setParameterValue(101);

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($parameter);

        self::assertCount(1, $violations);
        self::assertSame('parameterValue', $violations[0]->getPropertyPath());
        self::assertStringContainsString('0 et 100', (string) $violations[0]->getMessage());
    }

    public function testSymfonyConstraintRequiresIntegersForDiscreteParameters(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey(AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY)
            ->setParameterValue(2.5);

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($parameter);

        self::assertCount(1, $violations);
        self::assertStringContainsString('entier', (string) $violations[0]->getMessage());
    }
}
