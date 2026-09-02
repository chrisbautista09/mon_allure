<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminTrainingPlanProgressAssetTest extends KernelTestCase
{
    public function testProgressScoreHasNumericAccessibleAndCriticalVisualRepresentations(): void
    {
        self::bootKernel();
        $projectDirectory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);
        $script = file_get_contents($projectDirectory.'/assets/controllers/admin_training_plans.js');
        self::assertIsString($script);

        self::assertStringContainsString('Number(plan.progress_score)', $script);
        self::assertStringContainsString('score < 30 ? "LOW"', $script);
        self::assertStringContainsString('"progress-bar ', $script);
        self::assertStringContainsString('setAttribute("role", "progressbar")', $script);
        self::assertStringContainsString('setAttribute("aria-valuenow"', $script);
        self::assertStringContainsString('styles[level].bar', $script);
    }

    public function testPlansAreInitializedAfterTurboNavigation(): void
    {
        self::bootKernel();
        $projectDirectory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);
        $script = file_get_contents($projectDirectory.'/assets/controllers/admin_training_plans.js');
        self::assertIsString($script);

        self::assertStringContainsString('const initializeAdminTrainingPlans = (root)', $script);
        self::assertStringContainsString('document.addEventListener("turbo:load", initializeAllAdminTrainingPlans)', $script);
        self::assertStringContainsString('document.addEventListener("turbo:before-cache"', $script);
        self::assertStringContainsString('root.dataset.adminTrainingPlansInitialized', $script);
    }
}
