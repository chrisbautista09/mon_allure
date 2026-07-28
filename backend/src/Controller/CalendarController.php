<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route(
        '/api/calendar/events',
        name: 'app_calendar_events',
        methods: ['GET']
    )]
    public function events(Request $request): JsonResponse
    {
        /*
         * FullCalendar ajoute automatiquement les paramètres start et end.
         * Nous les récupérons dès maintenant pour préparer la future requête
         * sur SessionRepository.
         */
        $start = new DateTimeImmutable(
            $request->query->get('start', '2026-07-01')
        );

        $end = new DateTimeImmutable(
            $request->query->get('end', '2026-08-01')
        );

        $events = [
            [
                'id' => 1,
                'title' => 'Repos complet',
                'start' => '2026-07-06',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 1]
                ),
                'classNames' => ['training-event', 'training-event--recovery'],
            ],
            [
                'id' => 2,
                'title' => 'VMA courte',
                'start' => '2026-07-07',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 2]
                ),
                'classNames' => ['training-event', 'training-event--vma'],
            ],
            [
                'id' => 3,
                'title' => 'Footing récupération',
                'start' => '2026-07-08',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 3]
                ),
                'classNames' => ['training-event', 'training-event--endurance'],
            ],
            [
                'id' => 4,
                'title' => 'Séance de côtes',
                'start' => '2026-07-09',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 4]
                ),
                'classNames' => ['training-event', 'training-event--threshold'],
            ],
            [
                'id' => 5,
                'title' => 'Repos ou étirements',
                'start' => '2026-07-10',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 5]
                ),
                'classNames' => ['training-event', 'training-event--recovery'],
            ],
            [
                'id' => 6,
                'title' => 'Footing plaisir',
                'start' => '2026-07-11',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 6]
                ),
                'classNames' => ['training-event', 'training-event--endurance'],
            ],
            [
                'id' => 7,
                'title' => 'Sortie longue',
                'start' => '2026-07-12',
                'allDay' => true,
                'url' => $this->generateUrl(
                    'app_training_daily',
                    ['id' => 7]
                ),
                'classNames' => ['training-event', 'training-event--long-run'],
            ],
        ];

        /*
         * Ces variables seront utilisées plus tard lorsque les événements
         * viendront véritablement de la base.
         */
        unset($start, $end);

        return $this->json($events);
    }
}