<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\IntensityZoneRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoDataService
{
    public const PASSWORD = 'DemoMonAllure2026!';

    /** @var array<string, array{vmaMin: float, vmaMax: float, fcmMin: float, fcmMax: float}> */
    private const INTENSITY_ZONES = [
        'Z1' => ['vmaMin' => 0.50, 'vmaMax' => 0.65, 'fcmMin' => 50.0, 'fcmMax' => 60.0],
        'Z2' => ['vmaMin' => 0.65, 'vmaMax' => 0.75, 'fcmMin' => 60.0, 'fcmMax' => 70.0],
        'Z3' => ['vmaMin' => 0.75, 'vmaMax' => 0.85, 'fcmMin' => 70.0, 'fcmMax' => 80.0],
        'Z4' => ['vmaMin' => 0.85, 'vmaMax' => 0.95, 'fcmMin' => 80.0, 'fcmMax' => 90.0],
        'Z5' => ['vmaMin' => 0.95, 'vmaMax' => 1.05, 'fcmMin' => 90.0, 'fcmMax' => 100.0],
    ];

    /** @var list<string> */
    public const SEEDED_EMAILS = [
        'karim@demo.mon-allure.fr',
        'julien@demo.mon-allure.fr',
        'elodie@demo.mon-allure.fr',
        'admin@demo.mon-allure.fr',
    ];

    public const VISITOR_EMAIL = 'sophie@demo.mon-allure.fr';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly IntensityZoneRepository $intensityZoneRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{users: int, plans: int, sessions: int, performances: int, visitorEmail: string} */
    public function seed(): array
    {
        foreach (self::SEEDED_EMAILS as $email) {
            if ($this->userRepository->findOneBy(['email' => $email]) instanceof User) {
                throw new \LogicException('Les données de démonstration existent déjà. Utilisez app:demo:reset.');
            }
        }

        $this->ensureIntensityZones();

        $anchor = new \DateTimeImmutable('monday this week');
        $counts = ['users' => 0, 'plans' => 0, 'sessions' => 0, 'performances' => 0];

        $karim = $this->createPersona('karim@demo.mon-allure.fr', 'karim_demo', 'Karim', 'Mansouri', 31, 13.0, 189, 54, 'Lyon', '-18 months');
        $julien = $this->createPersona('julien@demo.mon-allure.fr', 'julien_demo', 'Julien', 'Morel', 44, 15.5, 178, 48, 'Paris', '-6 years');
        $elodie = $this->createPersona('elodie@demo.mon-allure.fr', 'elodie_demo', 'Élodie', 'Roux', 35, 13.5, 185, 52, 'Annecy', '-3 years');
        $admin = $this->createUser('admin@demo.mon-allure.fr', 'admin_demo', ['ROLE_ADMIN'], '-2 years');
        $counts['users'] = 4;

        $this->addPlan($karim, $counts, 'Préparation 10 km', 'intermediate', 'road', 10, 55, $anchor->modify('-32 weeks'), 12, false, 100);
        $this->addPlan($karim, $counts, 'Premier semi-marathon', 'intermediate', 'road', 21.1, null, $anchor->modify('-6 weeks'), 16, true, 38);

        $this->addPlan($julien, $counts, 'Objectif semi-marathon 1 h 45', 'performance', 'road', 21.1, 105, $anchor->modify('-70 weeks'), 14, false, 100);
        $this->addPlan($julien, $counts, 'Marathon terminé en 4 h 12', 'performance', 'road', 42.195, 252, $anchor->modify('-38 weeks'), 18, false, 100);
        $this->addPlan($julien, $counts, 'Marathon en moins de 4 h', 'performance', 'road', 42.195, 239, $anchor->modify('-7 weeks'), 18, true, 44);

        $this->addPlan($elodie, $counts, 'Préparation trail découverte', 'intermediate', 'trail', 15, null, $anchor->modify('-30 weeks'), 10, false, 100, 650);
        $this->addPlan($elodie, $counts, 'Trail 25 km - 1 200 m D+', 'intermediate', 'trail', 25, null, $anchor->modify('-5 weeks'), 14, true, 36, 1200);

        $this->addComment($karim, 'Le calendrier est très clair. J’aimerais pouvoir comparer deux semaines côte à côte.', '-12 days');
        $this->addComment($julien, 'L’adaptation après une séance manquée m’a permis de conserver une charge cohérente.', '-7 days');
        $this->addComment($elodie, 'Les indications de dénivelé sont utiles pour préparer mes sorties trail.', '-2 days');

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return [...$counts, 'visitorEmail' => self::VISITOR_EMAIL];
    }

    /** @return array{users: int, plans: int, sessions: int, performances: int, visitorEmail: string} */
    public function reset(): array
    {
        foreach ([...self::SEEDED_EMAILS, self::VISITOR_EMAIL] as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $this->entityManager->remove($user);
            }
        }
        $this->entityManager->flush();

        return $this->seed();
    }

    private function createPersona(
        string $email,
        string $pseudo,
        string $firstName,
        string $lastName,
        int $age,
        float $vma,
        int $fcm,
        int $fcr,
        string $city,
        string $createdModifier,
    ): User {
        $user = $this->createUser($email, $pseudo, [], $createdModifier);
        $profile = (new Profile())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setAge($age)
            ->setVma($vma)
            ->setVo2max(round($vma * 3.5, 1))
            ->setFcm($fcm)
            ->setFcr($fcr)
            ->setCity($city)
            ->setCountry('France')
            ->setUser($user);
        $user->setProfile($profile);

        return $user;
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $pseudo, array $roles, string $createdModifier): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setRoles($roles)
            ->setCreatedAt(new \DateTimeImmutable($createdModifier));
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        $this->entityManager->persist($user);

        return $user;
    }

    /** @param array{users: int, plans: int, sessions: int, performances: int} $counts */
    private function addPlan(
        User $user,
        array &$counts,
        string $name,
        string $pole,
        string $terrain,
        float $distance,
        ?int $targetMinutes,
        \DateTimeImmutable $start,
        int $weeks,
        bool $active,
        float $progress,
        ?int $elevation = null,
    ): void {
        $end = $start->modify(sprintf('+%d days', ($weeks * 7) - 1));
        $today = new \DateTimeImmutable('today');
        $elapsedWeeks = max(1, min($weeks, (int) floor(($today->getTimestamp() - $start->getTimestamp()) / 604800) + 1));
        $feasibility = $progress < 40 ? 'MOYEN' : ($pole === 'performance' ? 'OPTIMAL' : 'BON');
        $plan = (new TrainingPlan())
            ->setName($name)
            ->setPoleType($pole)
            ->setTargetType('distance')
            ->setTargetValue($distance)
            ->setTargetUnit('km')
            ->setTargetDurationMinutes($targetMinutes)
            ->setTerrainType($terrain)
            ->setElevationTargetDPlus($elevation)
            ->setFeasibilityIndicator($feasibility)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDurationWeeks($weeks)
            ->setCurrentWeek($active ? $elapsedWeeks : $weeks)
            ->setProgressScore($progress)
            ->setIsActive($active)
            ->setCreatedAt($start->modify('-10 days'));
        $user->addTrainingPlan($plan);
        ++$counts['plans'];

        if ($active && $elapsedWeeks >= 4) {
            $plan->addAdaptationHistory([
                'adaptedAt' => $start->modify('+20 days')->format(\DateTimeInterface::ATOM),
                'successRate' => 83.0,
                'successValidationRate' => 80.0,
                'decision' => 'progression',
                'loadFactor' => 1.05,
                'reason' => 'Bloc réussi avec une bonne régularité.',
                'modification' => 'Charge des séances futures augmentée de 5 %.',
            ]);
        }

        $sessionsPerWeek = $pole === 'performance' ? 5 : 3;
        for ($week = 1; $week <= $weeks; ++$week) {
            for ($slot = 0; $slot < $sessionsPerWeek; ++$slot) {
                $days = $sessionsPerWeek === 5 ? [0, 1, 3, 5, 6] : [1, 3, 6];
                $date = $start->modify(sprintf('+%d days', (($week - 1) * 7) + $days[$slot]));
                $types = ['endurance', 'active', 'threshold', 'vma', 'long_run'];
                $type = $types[$slot % count($types)];
                $status = $date < $today ? (($week + $slot) % 11 === 0 ? 'missed' : 'completed') : 'planned';
                $duration = 38 + ($slot * 9) + min(25, $week * 2);
                $plannedDistance = round(($duration / 60) * (8.5 + ($slot * 0.8)), 1);
                $session = (new Session())
                    ->setWeekIndex($week)
                    ->setDayOfWeek($days[$slot] + 1)
                    ->setTitle($this->sessionTitle($type, $terrain))
                    ->setInstructions('Échauffement progressif, bloc principal contrôlé puis retour au calme.')
                    ->setSessionType($type)
                    ->setPlannedDistanceKm($plannedDistance)
                    ->setPlannedDurationMin($duration)
                    ->setPlannedElevationDPlus($terrain === 'trail' ? 80 + ($week * 12) : null)
                    ->setPlannedVmaCoef([0.68, 0.78, 0.88, 0.98, 0.72][$slot % 5])
                    ->setPlannedFcmZone(['Z2', 'Z3', 'Z4', 'Z5', 'Z2'][$slot % 5])
                    ->setDate($date)
                    ->setStatus($status);
                $session->addSessionIntensityZone(
                    (new SessionIntensityZone())
                        ->setIntensityZone($this->requiredIntensityZone($session->getPlannedFcmZone()))
                        ->setDurationPercent(100.0),
                );
                $plan->addSession($session);
                ++$counts['sessions'];

                if ($status === 'completed') {
                    $actualDistance = round($plannedDistance * (0.97 + ((($week + $slot) % 5) * 0.01)), 2);
                    $performance = (new Performance())
                        ->setDistanceKm($actualDistance)
                        ->setDurationSec(($duration * 60) + ((($week + $slot) % 7) * 11))
                        ->setElevationDPlus($terrain === 'trail' ? 75 + ($week * 11) : null)
                        ->setAvgHr(138 + (($week + $slot) % 22))
                        ->setComment(($week + $slot) % 6 === 0 ? 'Séance exigeante mais terminée avec de bonnes sensations.' : 'Séance réalisée conformément au plan.')
                        ->setCreatedAt($date->setTime(19, 15))
                        ->setSession($session)
                        ->setUser($user);
                    $user->addPerformance($performance);
                    ++$counts['performances'];
                }
            }
        }
    }

    private function requiredIntensityZone(?string $name): IntensityZone
    {
        $zone = $this->intensityZoneRepository->findOneBy(['name' => $name]);

        if (!$zone instanceof IntensityZone) {
            throw new \LogicException(sprintf('La zone d’intensité %s est introuvable.', $name ?? 'inconnue'));
        }

        return $zone;
    }

    private function ensureIntensityZones(): void
    {
        $created = false;

        foreach (self::INTENSITY_ZONES as $name => $values) {
            if ($this->intensityZoneRepository->findOneBy(['name' => $name]) instanceof IntensityZone) {
                continue;
            }

            $this->entityManager->persist((new IntensityZone())
                ->setName($name)
                ->setVmaCoefMin($values['vmaMin'])
                ->setVmaCoefMax($values['vmaMax'])
                ->setFcmPercentMin($values['fcmMin'])
                ->setFcmPercentMax($values['fcmMax']));
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    private function sessionTitle(string $type, string $terrain): string
    {
        return match ($type) {
            'active' => 'Allure active contrôlée',
            'threshold' => 'Travail au seuil',
            'vma' => 'Intervalles VMA',
            'long_run' => $terrain === 'trail' ? 'Sortie longue avec dénivelé' : 'Sortie longue progressive',
            default => 'Endurance fondamentale',
        };
    }

    private function addComment(User $user, string $content, string $dateModifier): void
    {
        $comment = (new Comment())
            ->setContent($content)
            ->setCreatedAt(new \DateTimeImmutable($dateModifier));
        $user->addComment($comment);
    }
}
