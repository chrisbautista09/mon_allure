<?php

namespace App\Tests\Functional;

use App\Entity\Profile;
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

    public function testAuthenticatedUserCanSaveDistanceGoal(): void
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

        self::assertResponseRedirects('/training-goal');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.training-goal__success', 'Votre objectif est prêt');
        self::assertSelectorTextContains('.training-goal__saved', '21.1 km');
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
            ->setUser($user));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
    }
}
