<?php

namespace App\Service;

use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('admin')]
final class AdminActionLogger
{
    public const ACTION_ACTIVATE = 'user.activate';
    public const ACTION_DEACTIVATE = 'user.deactivate';
    public const ACTION_DELETE = 'user.delete';
    public const ACTION_PARAMETER_UPDATE = 'algorithm_parameter.update';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function log(string $action, User $administrator, User $target, ?int $targetId = null): void
    {
        if (!in_array($action, [self::ACTION_ACTIVATE, self::ACTION_DEACTIVATE, self::ACTION_DELETE], true)) {
            throw new \InvalidArgumentException('L’action administrative à journaliser est invalide.');
        }

        $this->logger->info('Administrative user action', [
            'action' => $action,
            'administrator' => [
                'id' => $administrator->getId(),
                'email' => $administrator->getEmail(),
            ],
            'target_user' => [
                'id' => $targetId ?? $target->getId(),
                'email' => $target->getEmail(),
            ],
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function logParameterChange(
        User $administrator,
        string $parameterKey,
        float $oldValue,
        float $newValue,
    ): void {
        $this->logger->info('Administrative algorithm parameter change', [
            'action' => self::ACTION_PARAMETER_UPDATE,
            'administrator' => [
                'id' => $administrator->getId(),
                'email' => $administrator->getEmail(),
            ],
            'parameter' => $parameterKey,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
