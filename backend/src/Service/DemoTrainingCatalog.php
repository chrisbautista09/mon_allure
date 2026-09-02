<?php

declare(strict_types=1);

namespace App\Service;

final class DemoTrainingCatalog
{
    /**
     * @return list<array{
     *     id: int,
     *     date: \DateTimeImmutable,
     *     dayOfWeek: int,
     *     sessionType: string,
     *     title: string,
     *     description: string,
     *     plannedDurationMin: int|null,
     *     plannedDistanceKm: float|null,
     *     plannedFcmZone: string,
     *     status: string
     * }>
     */
    public function sessions(): array
    {
        return [
            $this->session(1, '2026-07-06', 1, 'recovery', 'Repos complet', 'Journée d’assimilation destinée à favoriser la récupération après les séances précédentes.', null, null, 'Z1'),
            $this->session(2, '2026-07-07', 2, 'vma', 'VMA courte – 2 × 10 × 30”/30”', 'Échauffement de 20 minutes, deux séries de 10 accélérations de 30 secondes avec 30 secondes de récupération active, puis retour au calme de 10 minutes.', 50, 8.0, 'Z5'),
            $this->session(3, '2026-07-08', 3, 'endurance', 'Footing de récupération', 'Footing souple de 45 minutes en zone 1 ou zone 2, sans recherche de vitesse.', 45, 6.5, 'Z2'),
            $this->session(4, '2026-07-09', 4, 'threshold', 'Côtes courtes – 10 × 30 secondes', 'Échauffement progressif, puis 10 répétitions en côte avec récupération en descente et retour au calme de 10 minutes.', 55, 8.5, 'Z4'),
            $this->session(5, '2026-07-10', 5, 'recovery', 'Repos ou étirements', 'Repos complet ou séance légère de mobilité et d’étirements sans douleur.', null, null, 'Z1'),
            $this->session(6, '2026-07-11', 6, 'endurance', 'Footing plaisir', 'Course facile de 50 minutes à allure confortable, en restant capable de parler.', 50, 7.5, 'Z2'),
            $this->session(7, '2026-07-12', 7, 'long_run', 'Sortie longue avec allure cible', 'Sortie longue de 1 h 40 comprenant deux blocs de 15 minutes à allure cible, séparés par 5 minutes en endurance fondamentale.', 100, 15.0, 'Z3'),
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->sessions() as $session) {
            if ($session['id'] === $id) {
                return $session;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function session(
        int $id,
        string $date,
        int $dayOfWeek,
        string $type,
        string $title,
        string $description,
        ?int $duration,
        ?float $distance,
        string $zone,
    ): array {
        return [
            'id' => $id,
            'date' => new \DateTimeImmutable($date),
            'dayOfWeek' => $dayOfWeek,
            'sessionType' => $type,
            'title' => $title,
            'description' => $description,
            'plannedDurationMin' => $duration,
            'plannedDistanceKm' => $distance,
            'plannedFcmZone' => $zone,
            'status' => 'planned',
        ];
    }
}
