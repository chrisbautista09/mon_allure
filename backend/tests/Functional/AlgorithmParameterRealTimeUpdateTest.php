<?php

namespace App\Tests\Functional;

use App\DataFixtures\AlgorithmParameterFixtures;
use App\Dto\TrainingPlanDTO;
use App\Entity\AlgorithmParameter;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\AlgorithmParameterRepository;
use App\Service\AlgorithmParameterService;
use App\Service\FeasibilityService;
use App\Service\SessionGeneratorService;
use App\Service\TrainingPlanGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AlgorithmParameterRealTimeUpdateTest extends KernelTestCase
{
    public function testNewPlansUseUpdatedValuesWithoutChangingExistingPlans(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(AlgorithmParameterRepository::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        (new AlgorithmParameterFixtures($repository))->load($entityManager);
        $parameterService = new AlgorithmParameterService($repository);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $sessionGenerator = $this->createMock(SessionGeneratorService::class);
        $sessionGenerator->expects(self::exactly(2))->method('generate')->willReturn([]);
        $generator = new TrainingPlanGeneratorService(
            $parameterService,
            $validator,
            new FeasibilityService($parameterService),
            $sessionGenerator,
        );

        $existingPlan = $generator->generatePlan($this->user(), $this->goal());
        self::assertSame(8, $existingPlan->getDurationWeeks());

        $parameterService->updateParameters([
            AlgorithmParameter::KEY_DEFAULT_PLAN_MIN_WEEKS => 10,
        ]);
        $newPlan = $generator->generatePlan($this->user(), $this->goal());

        self::assertSame(10, $newPlan->getDurationWeeks());
        self::assertSame(8, $existingPlan->getDurationWeeks());
    }

    private function user(): User
    {
        $user = new User();
        $user->setProfile((new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(14)
            ->setFcm(185)
            ->setUser($user));

        return $user;
    }

    private function goal(): TrainingPlanDTO
    {
        $goal = new TrainingPlanDTO();
        $goal->poleType = 'discovery';
        $goal->targetType = 'distance';
        $goal->targetValue = 5;
        $goal->targetUnit = 'km';
        $goal->terrainType = 'road';

        return $goal;
    }
}
