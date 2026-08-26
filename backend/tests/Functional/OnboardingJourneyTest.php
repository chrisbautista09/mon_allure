<?php

namespace App\Tests\Functional;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OnboardingJourneyTest extends WebTestCase
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

    public function testCompleteRegistrationProfileAndGoalJourney(): void
    {
        $this->registerUser();
        $this->loginUser();
        $this->completePhysiologicalProfile();
        $this->defineTrainingGoal();

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'journey@example.com',
        ]);

        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Profile::class, $user->getProfile());
        self::assertSame(34, $user->getProfile()->getAge());
        self::assertSame(14.8, $user->getProfile()->getVma());
        self::assertSame('Toulouse, France', $user->getProfile()->getTrainingLocation());

        $this->client->request('GET', '/training-goal');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.training-goal__saved', '42.2 km');
        self::assertSelectorTextContains('.training-goal__saved', 'trail');
    }

    private function registerUser(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'journey@example.com',
            'registration_form[pseudo]' => 'journey-runner',
            'registration_form[plainPassword][first]' => 'SecurePass123!',
            'registration_form[plainPassword][second]' => 'SecurePass123!',
            'registration_form[agreeTerms]' => true,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
    }

    private function loginUser(): void
    {
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'journey@example.com',
            'password' => 'SecurePass123!',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/profile/calibration');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    private function completePhysiologicalProfile(): void
    {
        $crawler = $this->client->request('GET', '/profile/calibration');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Valider mon profil')->form([
            'profile[firstName]' => 'Camille',
            'profile[lastName]' => 'Martin',
            'profile[age]' => '34',
            'profile[city]' => 'Toulouse',
            'profile[postalCode]' => '31000',
            'profile[country]' => 'France',
            'profile[vma]' => '14.8',
            'profile[vo2max]' => '46.5',
            'profile[fcm]' => '188',
            'profile[fcr]' => '52',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/training-goal');
    }

    private function defineTrainingGoal(): void
    {
        $crawler = $this->client->request('GET', '/training-goal');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Valider mon objectif')->form([
            'training_goal[poleType]' => 'performance',
            'training_goal[targetType]' => 'distance',
            'training_goal[targetValue]' => '42.2',
            'training_goal[targetUnit]' => 'km',
            'training_goal[terrainType]' => 'trail',
            'training_goal[elevationTargetDPlus]' => '950',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/training-goal');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.training-goal__success', 'Votre objectif est prêt');
    }
}
