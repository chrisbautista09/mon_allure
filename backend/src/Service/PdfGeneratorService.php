<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use App\Entity\Session;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final class PdfGeneratorService
{
    private const string TRAINING_PLAN_TEMPLATE = 'pdf/training_plan.html.twig';

    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function generateTrainingPlanPdf(TrainingPlan $plan): string
    {
        $html = $this->twig->render(self::TRAINING_PLAN_TEMPLATE, [
            'plan' => $plan,
            'logoDataUri' => $this->logoDataUri(),
            'sessionsByWeek' => $this->sessionsByWeek($plan),
        ]);
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function logoDataUri(): ?string
    {
        $logoPath = $this->projectDir.'/public/images/logo Mon Allure.png';
        $logo = is_file($logoPath) ? file_get_contents($logoPath) : false;

        return $logo === false ? null : 'data:image/png;base64,'.base64_encode($logo);
    }

    /** @return array<int, list<Session>> */
    private function sessionsByWeek(TrainingPlan $plan): array
    {
        $sessions = $plan->getSessions()->toArray();
        usort(
            $sessions,
            static fn (Session $left, Session $right): int =>
                ($left->getDate()?->getTimestamp() ?? PHP_INT_MAX)
                <=> ($right->getDate()?->getTimestamp() ?? PHP_INT_MAX),
        );
        $sessionsByWeek = [];

        foreach ($sessions as $session) {
            $sessionsByWeek[(int) $session->getWeekIndex()][] = $session;
        }

        return $sessionsByWeek;
    }
}
