<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\WeatherUnavailableException;
use App\Repository\TrainingPlanRepository;
use App\Service\FormStatusService;
use App\Service\TrainingBalanceService;
use App\Service\WeatherAdviceService;
use App\Service\WeatherService;
use App\Service\ZoneDistributionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
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

    #[Route('/intensity-zones', name: 'intensity_zones', methods: ['GET'])]
    public function intensityZones(
        Request $request,
        ClockInterface $clock,
        ZoneDistributionService $zoneDistributionService,
        TrainingBalanceService $trainingBalanceService,
        TrainingPlanRepository $trainingPlanRepository,
    ): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $period = strtolower(trim($request->query->getString('period', 'all')));
        $scope = strtolower(trim($request->query->getString('scope', 'active')));
        $today = \DateTimeImmutable::createFromInterface($clock->now())->setTime(0, 0);
        $start = match ($period) {
            'all' => null,
            '30d' => $today->modify('-29 days'),
            '3m' => $today->modify('-3 months +1 day'),
            '6m' => $today->modify('-6 months +1 day'),
            '1y' => $today->modify('-1 year +1 day'),
            default => false,
        };

        if ($start === false || !in_array($scope, ['active', 'all'], true)) {
            return $this->json([
                'message' => 'Les filtres demandés sont invalides.',
                'allowedPeriods' => ['30d', '3m', '6m', '1y', 'all'],
                'allowedScopes' => ['active', 'all'],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activePlan = $trainingPlanRepository->findLatestActiveOwnedWithSessions($user);
        $statistics = $scope === 'active' && $activePlan !== null
            ? $zoneDistributionService->getPlanStatistics($activePlan, $start, $start === null ? null : $today)
            : $zoneDistributionService->getStatistics($user, $start, $start === null ? null : $today);
        $balance = match (true) {
            $scope === 'all' => [
                'status' => 'unavailable',
                'message' => 'Sélectionnez le plan actif pour analyser son équilibre.',
                'actual' => ['endurance' => 0.0, 'threshold' => 0.0, 'vma' => 0.0],
                'reference' => null,
            ],
            $activePlan === null => [
                'status' => 'unavailable',
                'message' => 'Aucun plan actif à analyser.',
                'actual' => ['endurance' => 0.0, 'threshold' => 0.0, 'vma' => 0.0],
                'reference' => null,
            ],
            default => $trainingBalanceService->analyze($activePlan, $start, $start === null ? null : $today),
        };

        return $this->json([
            'data_state' => $statistics['zones'] === [] ? 'EMPTY' : 'READY',
            'total_sessions' => $statistics['totalSessions'],
            'dominant_zone' => $statistics['dominantZone'],
            'zones' => array_map(static fn (array $zone): array => [
                'name' => $zone['zone'],
                'label' => self::zoneLabel($zone['zone']),
                'sessions' => $zone['sessions'],
                'percentage' => $zone['percentage'],
            ], $statistics['zones']),
            'filters' => ['period' => $period, 'scope' => $scope],
            'balance' => $balance,
        ]);
    }

    private static function zoneLabel(string $zone): string
    {
        return match ($zone) {
            'Z1' => 'Récupération',
            'Z2' => 'Endurance',
            'Z3' => 'Tempo',
            'Z4' => 'Seuil',
            'Z5' => 'VMA',
            default => $zone,
        };
    }

    private function invalidLocationResponse(): JsonResponse
    {
        return $this->json([
            'dataState' => 'INVALID_LOCATION',
            'message' => 'Les coordonnées fournies sont invalides.',
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
