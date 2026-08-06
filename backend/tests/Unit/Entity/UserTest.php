<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalized(): void
    {
        $user = new User();

        $user->setEmail('  Coureur@Example.COM  ');

        self::assertSame(
            'coureur@example.com',
            $user->getEmail()
        );
    }
}
