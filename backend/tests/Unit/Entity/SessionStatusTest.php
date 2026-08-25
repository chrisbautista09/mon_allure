<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Session;
use App\Enum\SessionStatus;
use PHPUnit\Framework\TestCase;

final class SessionStatusTest extends TestCase
{
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
        $session = (new Session())->setStatus(SessionStatus::DONE);

        self::assertTrue($session->isSuccessful());
        self::assertFalse($session->isFailed());
        self::assertTrue($session->isTerminal());
        self::assertNotNull($session->getCompletedAt());
    }

    public function testPartialAndMissedSessionsAreFailures(): void
    {
        $partial = (new Session())->setStatus('partially_done');
        $missed = (new Session())->setStatus('missed');

        self::assertTrue($partial->isFailed());
        self::assertTrue($missed->isFailed());
        self::assertFalse($partial->isSuccessful());
        self::assertFalse($missed->isSuccessful());
    }

    public function testReturningToPlannedClearsCompletionDate(): void
    {
        $session = (new Session())
            ->setStatus('done')
            ->setStatus('planned');

        self::assertNull($session->getCompletedAt());
        self::assertFalse($session->isTerminal());
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Session())->setStatus('completed');
    }
}
