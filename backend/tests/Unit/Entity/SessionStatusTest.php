<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Session;
use App\Enum\SessionStatus;
use PHPUnit\Framework\TestCase;

final class SessionStatusTest extends TestCase
{
    public function testEnumDefinesHistoryStatuses(): void
    {
        self::assertSame(
            ['planned', 'completed', 'missed', 'cancelled'],
            array_map(static fn (SessionStatus $status): string => $status->value, SessionStatus::cases()),
        );
    }

    public function testPlannedSessionIsNeitherSuccessfulNorFailed(): void
    {
        $session = new Session();

        self::assertSame(SessionStatus::PLANNED, $session->getStatusValue());
        self::assertFalse($session->isSuccessful());
        self::assertFalse($session->isFailed());
        self::assertFalse($session->isTerminal());
        self::assertNull($session->getCompletedAt());
    }

    public function testDoneSessionIsSuccessfulAndTracksCompletionDate(): void
    {
        $session = (new Session())->setStatus(SessionStatus::COMPLETED);

        self::assertTrue($session->isSuccessful());
        self::assertFalse($session->isFailed());
        self::assertTrue($session->isTerminal());
        self::assertNotNull($session->getCompletedAt());
    }

    public function testMissedAndCancelledSessionsAreFailures(): void
    {
        $missed = (new Session())->setStatus('missed');
        $cancelled = (new Session())->setStatus('cancelled');

        self::assertTrue($missed->isFailed());
        self::assertTrue($cancelled->isFailed());
        self::assertFalse($missed->isSuccessful());
        self::assertFalse($cancelled->isSuccessful());
    }

    public function testReturningToPlannedClearsCompletionDate(): void
    {
        $session = (new Session())
            ->setStatus('completed')
            ->setStatus('planned');

        self::assertNull($session->getCompletedAt());
        self::assertFalse($session->isTerminal());
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Session())->setStatus('done');
    }
}
