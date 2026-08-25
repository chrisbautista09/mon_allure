<?php

namespace App\Tests\Integration;

use Dompdf\Dompdf;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DompdfInstallationTest extends TestCase
{
    public function testDompdfGeneratesPdfFromTwigTemplate(): void
    {
        $twig = new Environment(new ArrayLoader([
            'validation.html.twig' => <<<'TWIG'
                <!doctype html>
                <html lang="fr">
                    <head><meta charset="UTF-8"><title>{{ title }}</title></head>
                    <body><h1>{{ title }}</h1><p>{{ message }}</p></body>
                </html>
                TWIG,
        ]));
        $html = $twig->render('validation.html.twig', [
            'title' => 'Mon Allure',
            'message' => 'Export PDF opérationnel',
        ]);
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1_000, strlen($pdf));
        self::assertSame(1, $dompdf->getCanvas()->get_page_count());
    }
}
