<?php

namespace App\Tests\Functional;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SessionHistoryPageTest extends WebTestCase
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
        $this->client->request('GET', '/history/sessions');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testNewUserWithoutTrainingPlanSeesAnEmptyHistory(): void
    {
        $user = (new User())
            ->setEmail('new-history-user@example.com')
            ->setPseudo('new-history-user')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="history-empty-state"]');
        self::assertSelectorTextContains('[data-testid="history-empty-state"]', 'Aucune séance');
        self::assertSelectorNotExists('[data-testid="history-session-list"]');
        self::assertSelectorNotExists('[data-testid="history-pagination"]');
    }

    public function testPageDisplaysOnlyAuthenticatedUsersPastSessions(): void
    {
        $owner = $this->persistUserWithSessions('history-page-owner@example.com', 2);
        $this->persistUserWithSessions('history-page-other@example.com', 1);
        $this->client->loginUser($owner);

        $crawler = $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Historique de mes séances');
        self::assertCount(2, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('body', 'Séance 2 history-page-owner@example.com');
        self::assertSelectorTextContains('body', 'Durée prévue');
        self::assertSelectorTextContains('body', '45 min');
        self::assertSelectorTextContains('body', 'Distance prévue');
        self::assertSelectorTextContains('body', '8.5 km');
        self::assertSelectorTextNotContains('body', 'history-page-other@example.com');
    }

    public function testPageProvidesPagination(): void
    {
        $user = $this->persistUserWithSessions('history-page-pages@example.com', 11);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('[data-testid="history-pagination"]', 'Page 1 sur 2');
        self::assertSelectorExists('[data-testid="history-pagination"] a[href*="page=2"]');

        $crawler = $this->client->request('GET', '/history/sessions?page=2');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('[data-testid="history-pagination"]', 'Page 2 sur 2');
    }

    public function testPageDisplaysAConsistentVisualBadgeForEachSessionStatus(): void
    {
        $user = $this->persistUserWithSessions(
            'history-page-statuses@example.com',
            4,
            ['completed', 'planned', 'missed', 'cancelled'],
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-status="COMPLETED"]', '● Complétée');
        self::assertSelectorExists('[data-status="COMPLETED"].badge-success');
        self::assertSelectorTextSame('[data-status="PLANNED"]', '● Planifiée');
        self::assertSelectorExists('[data-status="PLANNED"].badge-warning');
        self::assertSelectorTextSame('[data-status="MISSED"]', '● Manquée');
        self::assertSelectorExists('[data-status="MISSED"].badge-danger');
        self::assertSelectorTextSame('[data-status="CANCELLED"]', '● Annulée');
        self::assertSelectorExists('[data-status="CANCELLED"].badge-neutral');
    }

    public function testPageDisplaysCompactPerformanceDataAndKeepsSessionsWithoutIt(): void
    {
        $user = $this->persistUserWithSessions(
            'history-page-performance@example.com',
            2,
            ['completed', 'missed'],
            true,
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '[data-testid="session-performance"]');
        self::assertSelectorTextContains('[data-testid="session-performance"]', 'Performance réalisée');
        self::assertSelectorTextContains('[data-testid="session-performance"]', '8.7 km');
        self::assertSelectorTextContains('[data-testid="session-performance"]', '50 min 30 s');
        self::assertSelectorTextContains('[data-testid="session-performance"]', '125 m D+');
        self::assertSelectorTextContains('[data-testid="session-performance"]', 'Route');
        self::assertSelectorCount(1, '[data-testid="session-without-performance"]');
        self::assertSelectorTextSame('[data-testid="session-without-performance"]', 'Aucune performance enregistrée');
        self::assertSelectorCount(2, '[data-testid="history-session-list"] article');
    }

    public function testPageCombinesStatusPeriodAndTitleFilters(): void
    {
        $user = $this->persistUserWithSessions(
            'history-page-filters@example.com',
            4,
            ['completed', 'completed', 'missed', 'cancelled'],
        );
        $sessions = $user->getTrainingPlans()->first()->getSessions()->toArray();
        $sessions[0]->setTitle('Tempo ancien')->setDate(new \DateTimeImmutable('-40 days'));
        $sessions[1]->setTitle('Tempo récent')->setDate(new \DateTimeImmutable('-10 days'));
        $sessions[2]->setTitle('Tempo manqué')->setDate(new \DateTimeImmutable('-5 days'));
        $sessions[3]->setTitle('Sortie annulée')->setDate(new \DateTimeImmutable('-1 day'));
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            '/history/sessions?status=completed&period=30_days&search=tempo',
        );

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('body', 'Tempo récent');
        self::assertSelectorTextNotContains('body', 'Tempo ancien');
        self::assertSelectorTextNotContains('body', 'Tempo manqué');
        self::assertSelectorTextNotContains('body', 'Sortie annulée');
        self::assertSelectorExists('select[name="status"] option[value="completed"][selected]');
        self::assertSelectorExists('select[name="period"] option[value="30_days"][selected]');
        self::assertSelectorExists('input[name="search"][value="tempo"]');
    }

    public function testCompletedFilterReturnsOnlyCompletedSessions(): void
    {
        $user = $this->persistUserWithSessions(
            'history-page-completed@example.com',
            6,
            ['completed', 'missed', 'completed', 'cancelled', 'completed', 'planned'],
        );
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/history/sessions?status=completed');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorCount(3, '[data-status="COMPLETED"]');
        self::assertSelectorNotExists('[data-status="MISSED"]');
        self::assertSelectorNotExists('[data-status="CANCELLED"]');
        self::assertSelectorNotExists('[data-status="PLANNED"]');
    }

    public function testCompleteHistoryOfFiftySessionsIsSortedAndPaginated(): void
    {
        $user = $this->persistUserWithSessions('history-page-fifty@example.com', 50);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/history/sessions');

        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('[data-testid="history-pagination"]', 'Page 1 sur 5');
        $titles = $crawler->filter('[data-testid="history-session-list"] article h2')->each(
            static fn ($node): string => $node->text(),
        );
        self::assertSame('Séance 50 history-page-fifty@example.com', $titles[0]);
        self::assertSame('Séance 41 history-page-fifty@example.com', $titles[9]);

        $crawler = $this->client->request('GET', '/history/sessions?page=5');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('[data-testid="history-pagination"]', 'Page 5 sur 5');
    }

    public function testHistoryOfOneHundredSessionsKeepsStablePagination(): void
    {
        $user = $this->persistUserWithSessions('history-page-hundred@example.com', 100);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/history/sessions?page=10');

        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('[data-testid="history-session-list"] article'));
        self::assertSelectorTextContains('[data-testid="history-pagination"]', 'Page 10 sur 10');
        $titles = $crawler->filter('[data-testid="history-session-list"] article h2')->each(
            static fn ($node): string => $node->text(),
        );
        self::assertSame('Séance 10 history-page-hundred@example.com', $titles[0]);
        self::assertSame('Séance 1 history-page-hundred@example.com', $titles[9]);
    }

    /** @param list<string> $statuses */
    private function persistUserWithSessions(
        string $email,
        int $sessionCount,
        array $statuses = [],
        bool $withPerformance = false,
    ): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan '.$email)
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('-4 weeks'))
            ->setEndDate(new \DateTimeImmutable('+4 weeks'))
            ->setDurationWeeks(8);

        for ($index = 1; $index <= $sessionCount; ++$index) {
            $session = (new Session())
                ->setWeekIndex(1)
                ->setDayOfWeek(($index % 7) + 1)
                ->setTitle(sprintf('Séance %d %s', $index, $email))
                ->setInstructions('Consignes de la séance.')
                ->setSessionType('endurance')
                ->setPlannedDurationMin(45)
                ->setPlannedDistanceKm(8.5)
                ->setDate(new \DateTimeImmutable(sprintf('-%d days', $sessionCount - $index + 1)))
                ->setStatus($statuses[$index - 1] ?? ($index % 2 === 0 ? 'completed' : 'missed'));

            if ($withPerformance && $index === 1) {
                $performance = (new Performance())
                    ->setDistanceKm(8.7)
                    ->setDurationSec(3030)
                    ->setElevationDPlus(125)
                    ->setUser($user);
                $session->setPerformance($performance);
                $user->addPerformance($performance);
            }

            $plan->addSession($session);
        }

        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
