<?php

namespace App\Tests\Integration;

use App\Dto\FormStatus;
use App\Enum\FormStatusDataState;
use App\Enum\FormStatusLevel;
use App\Enum\FormTrend;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class FormStatusTemplateTest extends KernelTestCase
{
    public function testDisplaysCoherentScoreStatusAndTrend(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('dashboard/_form_status.html.twig', [
            'formStatus' => new FormStatus(
                82,
                FormStatusLevel::GOOD,
                FormTrend::IMPROVING,
                FormStatusDataState::LIMITED_DATA,
                4,
                new \DateTimeImmutable('2026-08-26 09:00:00'),
            ),
        ]);

        self::assertStringContainsString('data-form-status="GOOD"', $html);
        self::assertStringContainsString('Bonne forme', $html);
        self::assertStringContainsString('82', $html);
        self::assertStringContainsString('aria-valuenow="82"', $html);
        self::assertStringContainsString('style="width: 82%"', $html);
        self::assertStringContainsString('Tendance : Progression', $html);
        self::assertStringContainsString('Votre forme progresse.', $html);
        self::assertStringContainsString('from-emerald-500', $html);
    }

    public function testUnavailableStatusDisplaysGuidanceWithoutFakeGauge(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('dashboard/_form_status.html.twig', [
            'formStatus' => new FormStatus(
                null,
                null,
                FormTrend::UNKNOWN,
                FormStatusDataState::NO_PERFORMANCE,
                0,
                new \DateTimeImmutable('2026-08-26 09:00:00'),
            ),
        ]);

        self::assertStringContainsString('data-form-status="UNAVAILABLE"', $html);
        self::assertStringContainsString('État de forme indisponible', $html);
        self::assertStringContainsString('Réalisez vos premières séances', $html);
        self::assertStringContainsString('—', $html);
        self::assertStringNotContainsString('role="progressbar"', $html);
        self::assertStringNotContainsString('aria-valuenow="0"', $html);
    }
}
