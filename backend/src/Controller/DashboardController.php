<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\WeatherUnavailableException;
use App\Service\FormStatusService;
use App\Service\WeatherAdviceService;
use App\Service\WeatherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard', name: 'api_dashboard_')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/weather', name: 'weather', methods: ['GET'])]
    public function weather(
        Request $request,
        WeatherService $weatherService,
        WeatherAdviceService $weatherAdviceService,
    ): JsonResponse {
        $latitude = $request->query->get('latitude');
        $longitude = $request->query->get('longitude');

        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return $this->json([
                'dataState' => 'LOCATION_REQUIRED',
                'message' => 'Autorisez la localisation ou choisissez une ville pour afficher la météo.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return $this->invalidLocationResponse();
        }

        $configuredLocation = $this->getUser() instanceof User
            ? $this->getUser()->getProfile()?->getTrainingLocation()
            : null;
        $location = trim((string) $request->query->get(
            'location',
            $configuredLocation ?? 'Position actuelle',
        ));
        $location = $location !== '' ? mb_substr($location, 0, 100) : 'Position actuelle';

        try {
            $weather = $weatherService->getWeather((float) $latitude, (float) $longitude);
        } catch (\InvalidArgumentException) {
            return $this->invalidLocationResponse();
        } catch (WeatherUnavailableException) {
            return $this->json([
                'dataState' => 'UNAVAILABLE',
                'message' => 'La météo est temporairement indisponible. Votre dashboard reste accessible.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'location' => $location,
            'temperature' => $weather['temperature'],
            'condition' => $weather['condition'],
            'wind' => $weather['wind'],
            'advice' => $weatherAdviceService->getAdvice($weather),
            'freshness' => $weather['freshness'],
        ]);
    }

    #[Route('/form-status', name: 'form_status', methods: ['GET'])]
    public function formStatus(FormStatusService $formStatusService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $formStatus = $formStatusService->calculate($user);

        return $this->json([
            ...$formStatus->toArray(),
            'last_update' => $formStatus->calculatedAt->format('Y-m-d'),
        ]);
    }

    private function invalidLocationResponse(): JsonResponse
    {
        return $this->json([
            'dataState' => 'INVALID_LOCATION',
            'message' => 'Les coordonnées fournies sont invalides.',
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
