<?php

namespace App\Tests\Integration;

use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Service\PdfGeneratorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrainingPlanPdfTemplateTest extends KernelTestCase
{
    public function testTemplateDisplaysRunnerPlanAndEverySession(): void
    {
        self::bootKernel();
        $plan = $this->plan();
        $twig = self::getContainer()->get('twig');
        $logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents(
            self::getContainer()->getParameter('kernel.project_dir').'/public/images/logo Mon Allure.png',
        ));
        $html = $twig->render('pdf/training_plan.html.twig', [
            'plan' => $plan,
            'logoDataUri' => $logo,
            'sessionsByWeek' => [
                1 => [$plan->getSessions()->get(1)],
                2 => [$plan->getSessions()->get(0)],
            ],
        ]);

        self::assertStringContainsString('Camille Martin', $html);
        self::assertStringContainsString('Intermediate - 3 séances max/semaine', $html);
        self::assertStringContainsString('Semaine 1', $html);
        self::assertStringContainsString('Semaine 2', $html);
        self::assertStringContainsString('Endurance fondamentale', $html);
        self::assertStringContainsString('Travail au seuil', $html);
        self::assertStringContainsString('data:image/png;base64,', $html);
        self::assertStringContainsString('5:10 min/km', $html);
        self::assertStringContainsString('class="session long"', $html);
        self::assertStringContainsString('Fin des consignes longues.', $html);
        self::assertGreaterThan(
            strpos($html, 'Endurance fondamentale'),
            strpos($html, 'Travail au seuil'),
        );

        $pdf = (new PdfGeneratorService(
            $twig,
            self::getContainer()->getParameter('kernel.project_dir'),
        ))->generateTrainingPlanPdf($plan);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(5_000, strlen($pdf));
    }

    private function plan(): TrainingPlan
    {
        $user = (new User())
            ->setEmail('camille@example.com')
            ->setPseudo('camille-run')
            ->setPassword('test-password');
        $user->setProfile((new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15.5)
            ->setFcm(190));
        $plan = (new TrainingPlan())
            ->setName('Préparation 10 km')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('route')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-26'))
            ->setDurationWeeks(8)
            ->setProgressScore(72);
        $user->addTrainingPlan($plan);
        $plan->addSession($this->session(2, '2026-09-08', 'Travail au seuil', 'threshold'));
        $plan->addSession($this->session(
            1,
            '2026-09-01',
            'Endurance fondamentale',
            'endurance',
            str_repeat('Maintenez une allure régulière et maîtrisée. ', 14).'Fin des consignes longues.',
        ));

        return $plan;
    }

    private function session(
        int $week,
        string $date,
        string $title,
        string $type,
        string $description = 'Maintenez une allure régulière et maîtrisée.',
    ): Session {
        return (new Session())
            ->setWeekIndex($week)
            ->setDayOfWeek(2)
            ->setTitle($title)
            ->setDescription($description)
            ->setSessionType($type)
            ->setPlannedDistanceKm(8)
            ->setPlannedDurationMin(45)
            ->setPlannedElevationDPlus(80)
            ->setPlannedVmaCoef(0.75)
            ->setPlannedFcmZone('Z2')
            ->setDate(new \DateTimeImmutable($date));
    }
}
