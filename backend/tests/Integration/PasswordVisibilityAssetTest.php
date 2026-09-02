<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PasswordVisibilityAssetTest extends KernelTestCase
{
    public function testCheckboxTogglesPasswordFieldsAndSupportsTurbo(): void
    {
        self::bootKernel();
        $projectDirectory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);

        $script = file_get_contents($projectDirectory.'/assets/controllers/password_visibility.js');
        self::assertIsString($script);
        self::assertStringContainsString('toggle.checked ? "text" : "password"', $script);
        self::assertStringContainsString('toggle.addEventListener("change", updateVisibility)', $script);
        self::assertStringContainsString('document.addEventListener("turbo:load", initializePasswordVisibility)', $script);
        self::assertStringContainsString('toggle.checked = false', $script);
    }
}
