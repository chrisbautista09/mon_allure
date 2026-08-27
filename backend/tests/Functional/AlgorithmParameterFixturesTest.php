<?php

namespace App\Tests\Functional;

use App\DataFixtures\AlgorithmParameterFixtures;
use App\Entity\AlgorithmParameter;
use App\Repository\AlgorithmParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AlgorithmParameterFixturesTest extends KernelTestCase
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
    }

    public function testLoadsTheFourteenReferenceParameters(): void
    {
        $this->fixture()->load($this->entityManager);

        $parameters = $this->repository->findAll();
        self::assertCount(14, $parameters);
        self::assertSame(AlgorithmParameter::SUPPORTED_KEYS, array_keys(AlgorithmParameterFixtures::DEFAULTS));
        self::assertSame(2.0, $this->value(AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY));
        self::assertSame(3.0, $this->value(AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE));
        self::assertSame(5.0, $this->value(AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE));
        self::assertSame(8.0, $this->value(AlgorithmParameter::KEY_DEFAULT_PLAN_MIN_WEEKS));
        self::assertSame(18.0, $this->value(AlgorithmParameter::KEY_DEFAULT_PLAN_MAX_WEEKS));
    }

    public function testLoadingWithExistingDataDoesNotDuplicateOrOverwriteIt(): void
    {
        $fixture = $this->fixture();
        $fixture->load($this->entityManager);
        $progression = $this->repository->findOneBy([
            'parameterKey' => AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT,
        ]);
        self::assertInstanceOf(AlgorithmParameter::class, $progression);
        $progression->updateValue(7.5);
        $this->entityManager->flush();

        $fixture->load($this->entityManager);

        self::assertCount(14, $this->repository->findAll());
        self::assertSame(7.5, $this->value(AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT));
    }

    private function fixture(): AlgorithmParameterFixtures
    {
        return new AlgorithmParameterFixtures($this->repository);
    }

    private function value(string $key): float
    {
        $parameter = $this->repository->findOneBy(['parameterKey' => $key]);
        self::assertInstanceOf(AlgorithmParameter::class, $parameter);

        return $parameter->getParameterValue();
    }
}
