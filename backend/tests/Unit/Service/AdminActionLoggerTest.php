<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\AdminActionLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AdminActionLoggerTest extends TestCase
{
    #[DataProvider('actionProvider')]
    public function testLogsAdministrativeActionWithActorTargetAndDate(string $action): void
    {
        $administrator = (new User())->setEmail('admin@example.com');
        $target = (new User())->setEmail('target@example.com');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Administrative user action',
                self::callback(static function (array $context) use ($action): bool {
                    return $context['action'] === $action
                        && $context['administrator']['email'] === 'admin@example.com'
                        && $context['target_user']['email'] === 'target@example.com'
                        && (new \DateTimeImmutable($context['occurred_at'])) <= new \DateTimeImmutable();
                }),
            );

        (new AdminActionLogger($logger))->log($action, $administrator, $target);
    }

    public static function actionProvider(): iterable
    {
        yield 'activation' => [AdminActionLogger::ACTION_ACTIVATE];
        yield 'deactivation' => [AdminActionLogger::ACTION_DEACTIVATE];
        yield 'deletion' => [AdminActionLogger::ACTION_DELETE];
    }

    public function testRejectsUnknownAction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AdminActionLogger($this->createStub(LoggerInterface::class)))
            ->log('unknown', new User(), new User());
    }

    public function testPreservesCapturedTargetIdAfterDoctrineDeletion(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Administrative user action',
                self::callback(static fn (array $context): bool => $context['target_user']['id'] === 42),
            );

        (new AdminActionLogger($logger))->log(
            AdminActionLogger::ACTION_DELETE,
            new User(),
            new User(),
            42,
        );
    }

    public function testLogsParameterChangeWithOldAndNewValuesAdministratorAndDate(): void
    {
        $administrator = (new User())->setEmail('parameter-admin@example.com');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Administrative algorithm parameter change',
                self::callback(static function (array $context): bool {
                    return $context['action'] === AdminActionLogger::ACTION_PARAMETER_UPDATE
                        && $context['administrator']['email'] === 'parameter-admin@example.com'
                        && $context['parameter'] === 'progression_max_percent'
                        && $context['old_value'] === 10.0
                        && $context['new_value'] === 12.5
                        && (new \DateTimeImmutable($context['occurred_at'])) <= new \DateTimeImmutable();
                }),
            );

        (new AdminActionLogger($logger))->logParameterChange(
            $administrator,
            'progression_max_percent',
            10,
            12.5,
        );
    }
}
