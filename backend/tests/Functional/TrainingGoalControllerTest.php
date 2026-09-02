<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TrainingGoalControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/training-goal');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAuthenticatedUserCanGeneratePlanFromDistanceGoal(): void
    {
        $this->authenticateUser();
        $crawler = $this->client->request('GET', '/training-goal');

        $form = $crawler->selectButton('Valider mon objectif')->form([
            'training_goal[poleType]' => 'intermediate',
            'training_goal[targetType]' => 'distance',
            'training_goal[targetValue]' => '21.1',
            'training_goal[targetUnit]' => 'km',
            'training_goal[terrainType]' => 'road',
            'training_goal[elevationTargetDPlus]' => '120',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="user-dashboard"]');
        self::assertSelectorTextContains('body', 'Objectif 21.1 km');
        self::assertSame(1, $this->entityManager->getRepository(TrainingPlan::class)->count([]));
        self::assertSame(36, $this->entityManager->getRepository(Session::class)->count([]));
        self::assertNull($this->client->getRequest()->getSession()->get('training_goal'));
    }

    public function testIncompatibleUnitIsDisplayedAsFormError(): void
    {
        $this->authenticateUser();
        $crawler = $this->client->request('GET', '/training-goal');

        $form = $crawler->selectButton('Valider mon objectif')->form([
            'training_goal[poleType]' => 'discovery',
            'training_goal[targetType]' => 'distance',
            'training_goal[targetValue]' => '10',
            'training_goal[targetUnit]' => 'min',
            'training_goal[terrainType]' => 'path',
            'training_goal[elevationTargetDPlus]' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.training-goal-form__field--error',
            'Cette unité ne correspond pas au type d’objectif choisi.'
        );
    }

    public function testAuthenticatedUserCanGenerateRaceGoalWithDistanceAndTime(): void
    {
        $this->authenticateUser();
        $crawler = $this->client->request('GET', '/training-goal');

        $form = $crawler->selectButton('Valider mon objectif')->form([
            'training_goal[poleType]' => 'performance',
            'training_goal[targetType]' => 'race',
            'training_goal[targetValue]' => '10',
            'training_goal[targetUnit]' => 'km',
            'training_goal[targetDurationMinutes]' => '45',
            'training_goal[terrainType]' => 'road',
            'training_goal[elevationTargetDPlus]' => '0',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        self::assertSelectorExists('[data-testid="user-dashboard"]');
        self::assertSelectorTextContains('body', 'Épreuve 10 km en 45 min');

        $plan = $this->entityManager->getRepository(TrainingPlan::class)->findOneBy([]);
        self::assertInstanceOf(TrainingPlan::class, $plan);
        self::assertSame('race', $plan->getTargetType());
        self::assertSame(10.0, $plan->getTargetValue());
        self::assertSame('km', $plan->getTargetUnit());
        self::assertSame(45, $plan->getTargetDurationMinutes());
        self::assertSame(40, $this->entityManager->getRepository(Session::class)->count([]));
    }

    private function authenticateUser(): void
    {
        $user = (new User())
            ->setEmail('goal@example.com')
            ->setPseudo('goal-runner')
            ->setPassword('test-password');
        $user->setProfile((new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user));

        $this->entityManager->persist($user);
        $this->persistAlgorithmData();
        $this->entityManager->flush();
        $this->client->loginUser($user);
    }

    private function persistAlgorithmData(): void
    {
        foreach ([
            'default_plan_min_weeks' => 8,
            'default_plan_max_weeks' => 18,
            'max_sessions_discovery' => 2,
            'max_sessions_intermediate' => 3,
            'max_sessions_performance' => 5,
        ] as $key => $value) {
            $this->entityManager->persist((new AlgorithmParameter())
                ->setParameterKey($key)
                ->setParameterValue($value));
        }

        foreach ([
            ['Z1', 0.50, 0.65, 50, 60],
            ['Z2', 0.65, 0.75, 60, 70],
            ['Z4', 0.85, 0.95, 80, 90],
            ['Z5', 0.95, 1.05, 90, 100],
        ] as [$name, $vmaMin, $vmaMax, $fcmMin, $fcmMax]) {
            $this->entityManager->persist((new IntensityZone())
                ->setName($name)
                ->setVmaCoefMin($vmaMin)
                ->setVmaCoefMax($vmaMax)
                ->setFcmPercentMin($fcmMin)
                ->setFcmPercentMax($fcmMax));
        }
    }
}
