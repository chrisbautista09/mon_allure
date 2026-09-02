<?php

namespace App\Tests\Functional;

use App\Command\ResetDemoDataCommand;
use App\Command\SeedDemoDataCommand;
use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Service\DemoDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoDataCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testSeedCreatesRichPersonaDataAndKeepsSophieAvailable(): void
    {
        $tester = new CommandTester(self::getContainer()->get(SeedDemoDataCommand::class));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('4 comptes', $tester->getDisplay());
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => DemoDataService::VISITOR_EMAIL]));

        $julien = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'julien@demo.mon-allure.fr']);
        self::assertInstanceOf(User::class, $julien);
        self::assertSame(3, $julien->getTrainingPlans()->count());
        self::assertGreaterThan(100, $julien->getPerformances()->count());
        self::assertGreaterThan(150, $this->entityManager->getRepository(TrainingPlan::class)->findAll()[0]->getSessions()->count() + array_sum(array_map(
            static fn (TrainingPlan $plan): int => $plan->getSessions()->count(),
            $julien->getTrainingPlans()->toArray(),
        )));

        $karim = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'karim@demo.mon-allure.fr']);
        self::assertInstanceOf(User::class, $karim);
        $activePlan = $karim->getTrainingPlans()->filter(
            static fn (TrainingPlan $plan): bool => $plan->isActive(),
        )->first();
        self::assertInstanceOf(TrainingPlan::class, $activePlan);
        self::assertSame(
            $activePlan->getSessions()->count(),
            $this->entityManager->getRepository(SessionIntensityZone::class)->count([
                'session' => $activePlan->getSessions()->toArray(),
            ]),
        );
    }

    public function testResetOnlyReplacesKnownDemoAccounts(): void
    {
        (new CommandTester(self::getContainer()->get(SeedDemoDataCommand::class)))->execute([]);
        $realUser = (new User())
            ->setEmail('real-user@example.com')
            ->setPseudo('real_user')
            ->setPassword('existing-hash');
        $sophie = (new User())
            ->setEmail(DemoDataService::VISITOR_EMAIL)
            ->setPseudo('sophie_video')
            ->setPassword('existing-hash');
        $this->entityManager->persist($realUser);
        $this->entityManager->persist($sophie);
        $this->entityManager->flush();

        $tester = new CommandTester(self::getContainer()->get(ResetDemoDataCommand::class));
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertInstanceOf(User::class, $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'real-user@example.com']));
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => DemoDataService::VISITOR_EMAIL]));
        self::assertCount(4, $this->entityManager->getRepository(User::class)->findBy(['email' => DemoDataService::SEEDED_EMAILS]));
    }
}
