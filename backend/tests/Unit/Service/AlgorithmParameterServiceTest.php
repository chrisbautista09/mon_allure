<?php

namespace App\Tests\Unit\Service;

use App\Entity\AlgorithmParameter;
use App\Repository\AlgorithmParameterRepository;
use App\Service\AlgorithmParameterService;
use PHPUnit\Framework\TestCase;

final class AlgorithmParameterServiceTest extends TestCase
{
    public function testReturnsCurrentNumericParametersIndexedByKey(): void
    {
        $repository = $this->createStub(AlgorithmParameterRepository::class);
        $repository->method('findCurrent')->willReturn($this->entities([
            AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 10,
            AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => 4,
        ]));

        self::assertSame([
            AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 10.0,
            AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => 4.0,
        ], (new AlgorithmParameterService($repository))->getCurrentParameters());
    }

    public function testUpdateIsValidatedPersistedAndImmediatelyReloaded(): void
    {
        $repository = $this->createMock(AlgorithmParameterRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findCurrent')
            ->willReturnOnConsecutiveCalls(
                $this->entities([AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 10]),
                $this->entities([AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 7.5]),
            );
        $repository->expects(self::once())
            ->method('updateParameters')
            ->with([AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 7.5]);

        $updated = (new AlgorithmParameterService($repository))->updateParameters([
            AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 7.5,
        ]);

        self::assertSame(7.5, $updated[AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT]);
    }

    public function testEmptyUpdateReadsCurrentConfigurationWithoutWriting(): void
    {
        $repository = $this->createMock(AlgorithmParameterRepository::class);
        $repository->method('findCurrent')->willReturn($this->entities([
            AlgorithmParameter::KEY_COEF_ENDURANCE => 0.7,
        ]));
        $repository->expects(self::never())->method('updateParameters');

        self::assertSame(
            [AlgorithmParameter::KEY_COEF_ENDURANCE => 0.7],
            (new AlgorithmParameterService($repository))->updateParameters([]),
        );
    }

    public function testValidationRejectsUnknownAndNonFiniteValues(): void
    {
        $service = new AlgorithmParameterService($this->createStub(AlgorithmParameterRepository::class));

        try {
            $service->validateParameters(['unknown' => 1]);
            self::fail('Une clé inconnue doit être refusée.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('unknown', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->validateParameters([AlgorithmParameter::KEY_COEF_VMA => INF]);
    }

    public function testValidationRejectsParameterOutsideItsBounds(): void
    {
        $service = new AlgorithmParameterService($this->createStub(AlgorithmParameterRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('0 et 30');

        $service->validateParameters([AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 31]);
    }

    public function testValidationRejectsIncoherentPlanDurationBounds(): void
    {
        $service = new AlgorithmParameterService($this->createStub(AlgorithmParameterRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('durée maximale');

        $service->validateParameters([
            AlgorithmParameter::KEY_DEFAULT_PLAN_MIN_WEEKS => 18,
            AlgorithmParameter::KEY_DEFAULT_PLAN_MAX_WEEKS => 8,
        ]);
    }

    public function testValidationRejectsIncoherentPoleHierarchy(): void
    {
        $service = new AlgorithmParameterService($this->createStub(AlgorithmParameterRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Intermediate');

        $service->validateParameters([
            AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY => 4,
            AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE => 3,
            AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE => 5,
        ]);
    }

    /**
     * @param array<string, float|int> $values
     * @return array<string, AlgorithmParameter>
     */
    private function entities(array $values): array
    {
        $entities = [];
        foreach ($values as $key => $value) {
            $entities[$key] = (new AlgorithmParameter())
                ->setParameterKey($key)
                ->setParameterValue((float) $value);
        }

        return $entities;
    }
}
