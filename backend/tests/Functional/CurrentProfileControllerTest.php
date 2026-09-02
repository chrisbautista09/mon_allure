<?php

namespace App\Tests\Functional;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrentProfileControllerTest extends WebTestCase
{
    public function testUserSeesCompleteProfileWithoutModificationControls(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = (new User())
            ->setEmail('profile@example.com')
            ->setPseudo('trail-runner')
            ->setPassword('test-password');
        $profile = (new Profile())
            ->setUser($user)
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(35)
            ->setCity('Annecy')
            ->setPostalCode('74000')
            ->setCountry('France')
            ->setVma(15.5)
            ->setVo2max(52.4)
            ->setFcm(188)
            ->setFcr(48);
        $user->setProfile($profile);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="current-profile"]');
        self::assertSelectorTextContains('[data-testid="current-profile"]', 'Camille');
        self::assertSelectorTextContains('[data-testid="current-profile"]', 'Annecy');
        self::assertSelectorTextContains('[data-testid="current-profile"]', '15,5 km/h');
        self::assertSelectorTextContains('[data-testid="current-profile"]', '188 bpm');
        self::assertSelectorNotExists('main form');
        self::assertSelectorNotExists('main input, main textarea, main select');
        self::assertSelectorNotExists('main a[href="/profile/calibration"]');
    }
}
