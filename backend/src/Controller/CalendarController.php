<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Service\DemoTrainingCatalog;
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
    public function events(
        Request $request,
        SessionRepository $repository,
        DemoTrainingCatalog $demoTrainingCatalog,
    ): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(array_map(fn (array $session): array => [
                'id' => $session['id'],
                'title' => $session['title'],
                'start' => $session['date']->format('Y-m-d'),
                'allDay' => true,
                'url' => $this->generateUrl('app_training_daily', ['id' => $session['id']]),
                'classNames' => [
                    'training-event',
                    sprintf('training-event--%s', str_replace('_', '-', $session['sessionType'])),
                    'training-event--status-planned',
                ],
                'extendedProps' => [
                    'status' => 'planned',
                    'week' => 1,
                    'durationMinutes' => $session['plannedDurationMin'],
                    'distanceKm' => $session['plannedDistanceKm'],
                    'zone' => $session['plannedFcmZone'],
                ],
            ], $demoTrainingCatalog->sessions()));
        }

        try {
            $start = new DateTimeImmutable($request->query->getString('start', 'first day of this month'));
            $end = new DateTimeImmutable($request->query->getString('end', 'first day of next month'));
            $sessions = $repository->findCalendarSessionsOwnedBy($user, $start, $end);
        } catch (\Exception $exception) {
            return $this->json(['message' => 'La période du calendrier est invalide.'], 400);
        }

        return $this->json(array_map(fn (Session $session): array => [
            'id' => $session->getId(),
            'title' => $session->getTitle(),
            'start' => $session->getDate()?->format('Y-m-d'),
            'allDay' => true,
            'url' => $this->generateUrl('app_training_daily', ['id' => $session->getId()]),
            'classNames' => [
                'training-event',
                sprintf('training-event--%s', str_replace('_', '-', (string) $session->getSessionType())),
                sprintf('training-event--status-%s', $session->getStatus()),
            ],
            'extendedProps' => [
                'status' => $session->getStatus(),
                'week' => $session->getWeekIndex(),
                'durationMinutes' => $session->getPlannedDurationMin(),
                'distanceKm' => $session->getPlannedDistanceKm(),
                'zone' => $session->getPlannedFcmZone(),
            ],
        ], $sessions));
    }
}
