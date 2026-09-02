<?php

namespace App\Tests\Functional;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrentGoalControllerTest extends WebTestCase
{
    public function testPageDisplaysOnlyCurrentActiveGoalWithoutCreationAction(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = (new User())
            ->setEmail('goal@example.com')
            ->setPseudo('goal-runner')
            ->setPassword('test-password');
        $activePlan = (new TrainingPlan())
            ->setUser($user)
            ->setName('Trail 25 km')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(25)
            ->setTargetUnit('km')
            ->setTerrainType('trail')
            ->setElevationTargetDPlus(900)
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-01'))
            ->setDurationWeeks(9)
            ->setIsActive(true);
        $user->addTrainingPlan($activePlan);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/objective');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="current-goal"]');
        self::assertSelectorTextContains('[data-testid="current-goal-value"]', '25 km');
        self::assertSelectorTextContains('[data-testid="current-goal"]', 'D+ 900 m');
        self::assertSelectorNotExists('main form');
        self::assertSelectorNotExists('a[href="/training-goal"]');
    }
}
