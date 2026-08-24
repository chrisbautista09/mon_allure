<?php

namespace App\Service;

use App\Dto\PaceResult;
use App\Entity\IntensityZone;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Repository\IntensityZoneRepository;

class SessionGeneratorService
{
    public function __construct(
        private readonly IntensityZoneRepository $zoneRepository,
        private readonly PaceCalculatorService $paceCalculator,
    ) {
    }

    /** @return list<Session> */
    public function generate(TrainingPlan $plan, Profile $profile, int $sessionsPerWeek): array
    {
        $durationWeeks = $plan->getDurationWeeks();
        $startDate = $plan->getStartDate();

        if ($durationWeeks === null || $durationWeeks <= 0 || $startDate === null) {
            throw new \InvalidArgumentException('Le plan doit posséder une date de début et une durée valides.');
        }

        if (!$plan->getSessions()->isEmpty()) {
            throw new \LogicException('Les séances de ce plan ont déjà été générées.');
        }

        $blueprints = $this->sessionBlueprints($plan, $sessionsPerWeek);
        $zones = [];
        $paces = [];

        foreach (array_unique(array_column($blueprints, 'zone')) as $zoneName) {
            $zones[$zoneName] = $this->requiredZone($zoneName);
            $paces[$zoneName] = $this->paceCalculator->calculate($profile, $zones[$zoneName]);
        }

        $sessions = [];

        for ($week = 1; $week <= $durationWeeks; ++$week) {
            $weekStart = $startDate->modify(sprintf('+%d weeks', $week - 1));
            $recoveryWeek = $week % 4 === 0;

            foreach ($blueprints as $blueprint) {
                $duration = $recoveryWeek
                    ? $blueprint['recoveryDuration']
                    : min(
                        $blueprint['maximumDuration'],
                        $blueprint['baseDuration'] + (($week - 1) * $blueprint['weeklyIncrease']),
                    );

                $sessions[] = $this->createSession(
                    $plan,
                    $zones[$blueprint['zone']],
                    $paces[$blueprint['zone']],
                    $week,
                    $blueprint['day'],
                    $weekStart->modify(sprintf('+%d days', $blueprint['day'] - 1)),
                    $recoveryWeek && $blueprint['type'] === 'long_run'
                        ? 'Sortie longue allégée'
                        : $blueprint['title'],
                    $blueprint['type'],
                    $duration,
                    $blueprint['instruction'],
                    $blueprint['type'] === 'long_run'
                        ? $this->weeklyElevation($plan, $durationWeeks)
                        : null,
                );
            }
        }

        return $sessions;
    }

    /**
     * @return list<array{
     *     day: int,
     *     title: string,
     *     type: string,
     *     zone: string,
     *     baseDuration: int,
     *     recoveryDuration: int,
     *     maximumDuration: int,
     *     weeklyIncrease: int,
     *     instruction: string
     * }>
     */
    private function sessionBlueprints(TrainingPlan $plan, int $sessionsPerWeek): array
    {
        $poleType = $plan->getPoleType();
        $expectedSessions = match ($poleType) {
            'discovery' => 2,
            'intermediate' => 3,
            'performance' => 5,
            default => throw new \InvalidArgumentException('Le pôle du plan est invalide.'),
        };

        if ($sessionsPerWeek !== $expectedSessions) {
            throw new \LogicException(sprintf(
                'Le paramètre du pôle %s doit prévoir %d séances par semaine.',
                $poleType,
                $expectedSessions,
            ));
        }

        $endurance = [
            'day' => 2,
            'title' => 'Endurance fondamentale',
            'type' => 'endurance',
            'zone' => 'Z2',
            'baseDuration' => 40,
            'recoveryDuration' => 35,
            'maximumDuration' => 60,
            'weeklyIncrease' => 2,
            'instruction' => 'Courez à une allure confortable et régulière, en restant capable de parler.',
        ];
        $longRun = [
            'day' => 7,
            'title' => 'Sortie longue',
            'type' => 'long_run',
            'zone' => 'Z2',
            'baseDuration' => 65,
            'recoveryDuration' => 60,
            'maximumDuration' => 120,
            'weeklyIncrease' => 5,
            'instruction' => 'Maintenez une allure maîtrisée et hydratez-vous régulièrement.',
        ];

        if ($poleType === 'discovery') {
            return [$endurance, $longRun];
        }

        $threshold = [
            'day' => 4,
            'title' => 'Travail au seuil',
            'type' => 'threshold',
            'zone' => 'Z4',
            'baseDuration' => 40,
            'recoveryDuration' => 35,
            'maximumDuration' => 55,
            'weeklyIncrease' => 1,
            'instruction' => 'Alternez les fractions soutenues et les récupérations à allure lente.',
        ];

        if ($poleType === 'intermediate') {
            return [$endurance, $threshold, $longRun];
        }

        return [
            [
                'day' => 1,
                'title' => 'Récupération active',
                'type' => 'recovery',
                'zone' => 'Z1',
                'baseDuration' => 30,
                'recoveryDuration' => 25,
                'maximumDuration' => 40,
                'weeklyIncrease' => 1,
                'instruction' => 'Restez très relâché afin de favoriser la récupération.',
            ],
            array_merge($threshold, ['day' => 2]),
            array_merge($endurance, ['day' => 4]),
            [
                'day' => 5,
                'title' => 'Travail VMA',
                'type' => 'vma',
                'zone' => 'Z5',
                'baseDuration' => 40,
                'recoveryDuration' => 30,
                'maximumDuration' => 55,
                'weeklyIncrease' => 1,
                'instruction' => 'Réalisez des répétitions rapides avec une récupération complète entre les efforts.',
            ],
            array_merge($longRun, ['baseDuration' => 75]),
        ];
    }

    private function requiredZone(string $name): IntensityZone
    {
        $zone = $this->zoneRepository->findOneBy(['name' => $name]);

        if (!$zone instanceof IntensityZone) {
            throw new \LogicException(sprintf('La zone d’intensité %s est introuvable.', $name));
        }

        return $zone;
    }

    private function createSession(
        TrainingPlan $plan,
        IntensityZone $zone,
        PaceResult $pace,
        int $week,
        int $dayOfWeek,
        \DateTimeImmutable $date,
        string $title,
        string $type,
        int $durationMinutes,
        string $instruction,
        ?int $elevation = null,
    ): Session {
        $description = sprintf(
            '%s Cible : %s (%.2f km/h), zone %s, %d–%d bpm.',
            $instruction,
            $pace->pace,
            $pace->speed,
            $pace->heartRateZone,
            $pace->heartRateMin,
            $pace->heartRateMax,
        );

        $session = (new Session())
            ->setWeekIndex($week)
            ->setDayOfWeek($dayOfWeek)
            ->setTitle($title)
            ->setDescription($description)
            ->setSessionType($type)
            ->setPlannedDistanceKm(round($pace->speed * $durationMinutes / 60, 2))
            ->setPlannedDurationMin($durationMinutes)
            ->setPlannedElevationDPlus($elevation)
            ->setPlannedVmaCoef($pace->vmaPercent / 100)
            ->setPlannedFcmZone($pace->heartRateZone)
            ->setDate($date)
            ->setStatus('planned');

        $session->addSessionIntensityZone(
            (new SessionIntensityZone())
                ->setIntensityZone($zone)
                ->setDurationPercent(100.0)
        );
        $plan->addSession($session);

        return $session;
    }

    private function weeklyElevation(TrainingPlan $plan, int $durationWeeks): ?int
    {
        $target = $plan->getElevationTargetDPlus();

        return $target === null ? null : (int) round($target / $durationWeeks);
    }
}
