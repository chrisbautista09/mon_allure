<?php

namespace App\Tests\Integration;

use App\Entity\TrainingPlan;
use App\Service\ObjectiveCountdownService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class ObjectiveCountdownTemplateTest extends KernelTestCase
{
    public function testPlanWithoutEndDateDisplaysUndefinedObjectiveMessage(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        $plan = (new TrainingPlan())->setIsActive(false);

        $html = $twig->render('training_plan/_countdown.html.twig', [
            'plan' => $plan,
            'countdown' => [
                'days_remaining' => null,
                'weeks_remaining' => null,
                'status' => ObjectiveCountdownService::STATUS_NOT_DEFINED,
            ],
            'timelineComparison' => [
                'days_elapsed' => null,
                'days_remaining' => null,
                'total_days' => null,
                'elapsed_percentage' => null,
            ],
            'progressPercentage' => 0,
        ]);

        self::assertStringContainsString('data-countdown-status="not_defined"', $html);
        self::assertStringContainsString('Aucun objectif défini', $html);
        self::assertStringContainsString('Non renseignée', $html);
        self::assertStringNotContainsString('1970', $html);
    }
}
