<?php

namespace App\Controller\Admin;

use App\Service\PlanMonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/training-plans', name: 'api_admin_training_plans_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminTrainingPlanController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, PlanMonitoringService $monitoringService): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', 20);
        $feasibility = $request->query->getString('feasibility', 'ALL');
        $status = $request->query->getString('status', 'ALL');
        if (
            strtoupper($feasibility) === 'ALL'
            && in_array(strtoupper($status), ['OPTIMAL', 'ACCEPTABLE', 'IMPOSSIBLE'], true)
        ) {
            $feasibility = $status;
            $status = 'ALL';
        }

        try {
            $overview = $monitoringService->getPlansOverview(
                $page,
                $perPage,
                $feasibility,
                $request->query->getString('user'),
                $status,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['message' => $exception->getMessage()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->json([
            'items' => array_map(self::planData(...), $overview['plans']),
            'pagination' => [
                'page' => $overview['pagination']['page'],
                'perPage' => $overview['pagination']['perPage'],
                'total' => $overview['pagination']['total'],
                'totalPages' => $overview['pagination']['pages'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private static function planData(array $plan): array
    {
        return [
            'id' => $plan['id'],
            'name' => $plan['name'],
            'user' => $plan['user']['pseudo'] ?? $plan['user']['email'],
            'user_id' => $plan['user']['id'],
            'user_email' => $plan['user']['email'],
            'feasibility_indicator' => $plan['feasibilityIndicator'],
            'feasibility_status' => self::feasibilityStatus($plan['feasibilityIndicator']),
            'progress_score' => $plan['progressScore'],
            'target_type' => $plan['objective']['type'],
            'target_value' => $plan['objective']['value'],
            'target_unit' => $plan['objective']['unit'],
            'target_duration_minutes' => $plan['objective']['durationMinutes'],
            'terrain_type' => $plan['objective']['terrainType'],
            'current_week' => $plan['currentWeek'],
            'duration_weeks' => $plan['durationWeeks'],
            'status' => $plan['status'],
            'start_date' => $plan['startDate'],
            'end_date' => $plan['endDate'],
            'created_at' => $plan['createdAt'],
            'training_load' => $plan['trainingLoad'],
            'success_rate' => $plan['successRate'],
            'is_problematic' => $plan['isProblematic'],
            'anomaly_count' => $plan['anomalyCount'],
            'anomalies' => $plan['anomalies'],
            'monitoring_history' => $plan['monitoringHistory'],
        ];
    }

    private static function feasibilityStatus(?string $indicator): string
    {
        return match (strtoupper((string) $indicator)) {
            'OPTIMAL' => 'OPTIMAL',
            'BON', 'MOYEN', 'ACCEPTABLE' => 'ACCEPTABLE',
            'FAIBLE', 'IMPOSSIBLE' => 'IMPOSSIBLE',
            default => 'INCONNU',
        };
    }
}
