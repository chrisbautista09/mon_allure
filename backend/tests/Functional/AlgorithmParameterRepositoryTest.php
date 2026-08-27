<?php

namespace App\Tests\Functional;

use App\DataFixtures\AlgorithmParameterFixtures;
use App\Entity\AlgorithmParameter;
use App\Repository\AlgorithmParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AlgorithmParameterRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AlgorithmParameterRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(AlgorithmParameterRepository::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        (new AlgorithmParameterFixtures($this->repository))->load($this->entityManager);
    }

    public function testFindCurrentReturnsOneOrderedParameterPerSupportedKey(): void
    {
        $configuration = $this->repository->findCurrent();

        self::assertCount(14, $configuration);
        self::assertSame(AlgorithmParameter::SUPPORTED_KEYS, array_keys($configuration));
        foreach ($configuration as $key => $parameter) {
            self::assertSame($key, $parameter->getParameterKey());
        }
    }

    public function testFindCurrentIgnoresUnsupportedLegacyParameter(): void
    {
        $this->entityManager->persist((new AlgorithmParameter())
            ->setParameterKey('legacy_parameter')
            ->setParameterValue(42));
        $this->entityManager->flush();

        self::assertCount(14, $this->repository->findCurrent());
        self::assertArrayNotHasKey('legacy_parameter', $this->repository->findCurrent());
    }

    public function testUpdateParametersPersistsACompletePartialUpdate(): void
    {
        $initialDate = new \DateTimeImmutable('2026-01-01');
        $progression = $this->repository->findOneBy([
            'parameterKey' => AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT,
        ]);
        self::assertInstanceOf(AlgorithmParameter::class, $progression);
        $progression->setUpdatedAt($initialDate);
        $this->entityManager->flush();

        $this->repository->updateParameters([
            AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 7.5,
            AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => 3,
        ]);
        $this->entityManager->clear();
        $configuration = $this->repository->findCurrent();

        self::assertSame(7.5, $configuration[AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT]->getParameterValue());
        self::assertSame(3.0, $configuration[AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY]->getParameterValue());
        self::assertGreaterThan($initialDate, $configuration[AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT]->getUpdatedAt());
        self::assertSame(2.0, $configuration[AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY]->getParameterValue());
    }

    public function testUpdateParametersRejectsUnknownKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown_parameter');

        $this->repository->updateParameters(['unknown_parameter' => 10]);
    }

    public function testUpdateParametersFailsAtomicallyWhenParameterIsMissing(): void
    {
        $missing = $this->repository->findOneBy([
            'parameterKey' => AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY,
        ]);
        self::assertInstanceOf(AlgorithmParameter::class, $missing);
        $this->entityManager->remove($missing);
        $this->entityManager->flush();

        try {
            $this->repository->updateParameters([
                AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => 5,
                AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => 3,
            ]);
            self::fail('Une configuration incomplète doit être refusée.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('recovery_week_frequency', $exception->getMessage());
        }

        $this->entityManager->clear();
        $progression = $this->repository->findOneBy([
            'parameterKey' => AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT,
        ]);
        self::assertInstanceOf(AlgorithmParameter::class, $progression);
        self::assertSame(10.0, $progression->getParameterValue());
    }
}
