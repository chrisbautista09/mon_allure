<?php

namespace App\Tests\Unit\Service;

use App\Entity\TrainingPlan;
use App\Service\PdfGeneratorService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class PdfGeneratorServiceTest extends TestCase
{
    public function testGeneratesA4PdfFromTrainingPlanAndTwigTemplate(): void
    {
        $plan = (new TrainingPlan())->setName('Préparation 10 km');
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(self::once())
            ->method('render')
            ->with('pdf/training_plan.html.twig', [
                'plan' => $plan,
                'logoDataUri' => null,
                'sessionsByWeek' => [],
            ])
            ->willReturn(<<<'HTML'
                <!doctype html>
                <html lang="fr">
                    <head><meta charset="UTF-8"><title>Plan d'entraînement</title></head>
                    <body><h1>Préparation 10 km</h1></body>
                </html>
                HTML);

        $pdf = (new PdfGeneratorService($twig, '/missing-project'))->generateTrainingPlanPdf($plan);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1_000, strlen($pdf));
        self::assertStringContainsString('/MediaBox [0.000 0.000 595.280 841.890]', $pdf);
    }
}
