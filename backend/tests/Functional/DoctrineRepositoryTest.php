<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\Comment;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Repository\AlgorithmParameterRepository;
use App\Repository\CommentRepository;
use App\Repository\PerformanceRepository;
use App\Repository\ProfileRepository;
use App\Repository\SessionRepository;
use App\Repository\TrainingPlanRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @param class-string $entityClass
     * @param class-string<ServiceEntityRepository<object>> $repositoryClass
     */
    #[DataProvider('repositoryProvider')]
    public function testRepositoryIsRegisteredAndDefaultReadMethodsWork(
        string $entityClass,
        string $repositoryClass,
    ): void {
        $repository = $this->entityManager->getRepository($entityClass);

        self::assertInstanceOf($repositoryClass, $repository);
        self::assertSame([], $repository->findAll());
        self::assertSame(0, $repository->count([]));
        self::assertNull($repository->find(999_999));
    }

    /** @return iterable<string, array{class-string, class-string<ServiceEntityRepository<object>>}> */
    public static function repositoryProvider(): iterable
    {
        yield 'session' => [Session::class, SessionRepository::class];
        yield 'performance' => [Performance::class, PerformanceRepository::class];
        yield 'training plan' => [TrainingPlan::class, TrainingPlanRepository::class];
        yield 'profile' => [Profile::class, ProfileRepository::class];
        yield 'comment' => [Comment::class, CommentRepository::class];
        yield 'algorithm parameter' => [AlgorithmParameter::class, AlgorithmParameterRepository::class];
    }
}
