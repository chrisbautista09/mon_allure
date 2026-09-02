<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NavigationMenusAssetTest extends KernelTestCase
{
    public function testMenusCloseHalfASecondAfterMouseLeaves(): void
    {
        self::bootKernel();
        $projectDirectory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);

        $script = file_get_contents($projectDirectory.'/assets/controllers/navigation_menus.js');
        self::assertIsString($script);
        self::assertStringContainsString('const CLOSE_DELAY_MS = 500', $script);
        self::assertStringContainsString('menu.addEventListener("mouseleave", scheduleClose)', $script);
        self::assertStringContainsString('menu.addEventListener("mouseenter", cancelScheduledClose)', $script);
        self::assertStringContainsString('menu.open = false', $script);
        self::assertStringContainsString('document.addEventListener("turbo:load", initializeNavigationMenus)', $script);
    }
}
