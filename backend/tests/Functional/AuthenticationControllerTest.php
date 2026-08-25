<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\TrainingPlan;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationControllerTest extends WebTestCase
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

    public function testLoginPageContainsCompleteAccessibleForm(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertSelectorTextSame('h1', 'Se connecter');
        self::assertSelectorExists('form[method="post"]');
        self::assertSelectorExists('label[for="inputEmail"]');
        self::assertSelectorExists('input#inputEmail[name="email"][type="email"][autocomplete="email"][required]');
        self::assertSelectorExists('label[for="inputPassword"]');
        self::assertSelectorExists('input#inputPassword[name="password"][type="password"][autocomplete="current-password"][required]');
        self::assertSelectorExists('input[name="_csrf_token"][type="hidden"][value]:not([value=""])');
        self::assertSelectorTextContains('button[type="submit"]', 'Se connecter');
        self::assertSelectorExists('a[href="/register"]');
        self::assertSelectorExists('a[href="/"]');
    }

    public function testFailedLoginDisplaysErrorAndKeepsSubmittedEmail(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'unknown@example.com',
            'password' => 'WrongPassword123',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[role="alert"]');
        self::assertInputValueSame('email', 'unknown@example.com');
        self::assertNull($crawler->filter('input#inputPassword')->attr('value'));
    }

    public function testAuthenticatedUserCanLogoutAndSessionIsInvalidated(): void
    {
        $user = (new User())
            ->setEmail('logout@example.com')
            ->setPseudo('logout-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/calibration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="authenticated-user"]', 'logout-runner');
        $form = $crawler->selectButton('Se déconnecter')->form();
        self::assertSame('POST', $form->getMethod());
        self::assertNotEmpty($form->get('_csrf_token')->getValue());

        $this->client->submit($form);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="logout-button"]');

        $this->client->request('GET', '/profile/calibration');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testLogoutRejectsGetRequests(): void
    {
        $this->client->request('GET', '/logout');

        self::assertResponseStatusCodeSame(405);
    }

    public function testInvalidLogoutCsrfTokenDoesNotEndSession(): void
    {
        $user = (new User())
            ->setEmail('csrf-logout@example.com')
            ->setPseudo('csrf-logout-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalid-token']);

        self::assertNotSame('/', $this->client->getResponse()->headers->get('Location'));
        $this->client->request('GET', '/profile/calibration');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="logout-button"]');
    }

    #[DataProvider('publicRouteProvider')]
    public function testVisitorCanAccessPublicRoutes(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string}> */
    public static function publicRouteProvider(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'inscription' => ['/register'];
        yield 'connexion' => ['/login'];
    }

    #[DataProvider('privateRouteProvider')]
    public function testVisitorIsRedirectedFromPrivateRoutes(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseRedirects('http://localhost/login');
    }

    /** @return iterable<string, array{string}> */
    public static function privateRouteProvider(): iterable
    {
        yield 'profil sportif' => ['/profile/calibration'];
        yield 'objectif sportif' => ['/training-goal'];
        yield 'plan hebdomadaire' => ['/training/weekly'];
        yield 'API profil' => ['/api/profile'];
        yield 'API plans' => ['/api/training-plans/1'];
        yield 'API calendrier auparavant exposée' => ['/api/calendar/events'];
    }

    public function testAuthenticatedUserCanAccessPreviouslyExposedCalendarApi(): void
    {
        $user = (new User())
            ->setEmail('calendar-access@example.com')
            ->setPseudo('calendar-access-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/calendar/events');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testValidLoginRedirectsUserWithoutProfileToCalibration(): void
    {
        $user = $this->persistLoginUser('no-profile@example.com', 'no-profile-runner');

        $this->submitLogin((string) $user->getEmail());

        self::assertResponseRedirects('/profile/calibration');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="logout-button"]');
    }

    public function testValidLoginRedirectsUserWithoutPlanToTrainingGoal(): void
    {
        $user = $this->persistLoginUser('no-plan@example.com', 'no-plan-runner');
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user);
        $user->setProfile($profile);
        $this->entityManager->flush();

        $this->submitLogin((string) $user->getEmail());

        self::assertResponseRedirects('/training-goal');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="logout-button"]');
    }

    public function testValidLoginRedirectsFullyOnboardedUserToWeeklyPlan(): void
    {
        $user = $this->persistLoginUser('with-plan@example.com', 'with-plan-runner');
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user);
        $user->setProfile($profile);
        $user->addTrainingPlan(
            (new TrainingPlan())
                ->setName('Plan de connexion')
                ->setPoleType('discovery')
                ->setTargetType('distance')
                ->setTargetValue(5)
                ->setTargetUnit('km')
                ->setTerrainType('road')
                ->setFeasibilityIndicator('BON')
                ->setStartDate(new \DateTimeImmutable('today'))
                ->setEndDate(new \DateTimeImmutable('today +55 days'))
                ->setDurationWeeks(8),
        );
        $this->entityManager->flush();

        $this->submitLogin((string) $user->getEmail());

        self::assertResponseRedirects('/training/weekly');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#training-plan-title', 'Plan de connexion');
        self::assertSelectorExists('[data-testid="logout-button"]');
    }

    public function testKnownUserWithWrongPasswordRemainsAnonymous(): void
    {
        $user = $this->persistLoginUser('wrong-password@example.com', 'wrong-password-runner');

        $this->submitLogin((string) $user->getEmail(), 'WrongPassword123');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        self::assertSelectorNotExists('[data-testid="logout-button"]');
        $this->client->request('GET', '/profile/calibration');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testRememberMeCreatesPersistentSecureCookie(): void
    {
        $user = $this->persistLoginUser('remember@example.com', 'remember-runner');

        $this->submitLogin((string) $user->getEmail(), rememberMe: true);

        self::assertResponseRedirects('/profile/calibration');
        $rememberCookie = null;

        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'REMEMBERME') {
                $rememberCookie = $cookie;
                break;
            }
        }

        self::assertNotNull($rememberCookie);
        self::assertTrue($rememberCookie->isHttpOnly());
        self::assertSame('lax', strtolower((string) $rememberCookie->getSameSite()));
        self::assertGreaterThan(time() + 600_000, $rememberCookie->getExpiresTime());
    }

    public function testLoginWithoutRememberMeDoesNotCreatePersistentCookie(): void
    {
        $user = $this->persistLoginUser('session-only@example.com', 'session-only-runner');

        $this->submitLogin((string) $user->getEmail());

        self::assertResponseRedirects('/profile/calibration');
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'REMEMBERME') {
                self::assertLessThanOrEqual(time(), $cookie->getExpiresTime());
            }
        }
    }

    public function testDisabledAccountCannotLogin(): void
    {
        $user = $this->persistLoginUser('disabled@example.com', 'disabled-runner')
            ->setIsActive(false);
        $this->entityManager->flush();

        $this->submitLogin((string) $user->getEmail());

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Ce compte est désactivé');
        self::assertSelectorNotExists('[data-testid="logout-button"]');
        $this->client->request('GET', '/profile/calibration');
        self::assertResponseRedirects('http://localhost/login');
    }

    private function persistLoginUser(string $email, string $pseudo): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($passwordHasher->hashPassword($user, 'SecurePass123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function submitLogin(
        string $email,
        string $password = 'SecurePass123',
        bool $rememberMe = false,
    ): void {
        $crawler = $this->client->request('GET', '/login');
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if ($rememberMe) {
            $credentials['_remember_me'] = true;
        }

        $form = $crawler->selectButton('Se connecter')->form($credentials);

        $this->client->submit($form);
    }
}
