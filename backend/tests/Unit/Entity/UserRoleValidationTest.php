<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserRoleValidationTest extends KernelTestCase
{
    public function testAdministratorRoleIsAccepted(): void
    {
        self::bootKernel();
        $user = $this->validUser()->setRoles(['ROLE_ADMIN']);

        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($user));
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testUnknownRoleIsRejected(): void
    {
        self::bootKernel();
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate(
            $this->validUser()->setRoles(['ROLE_SUPER_ADMIN']),
        );

        self::assertGreaterThanOrEqual(1, $violations->count());
        self::assertSame('roles[0]', $violations[0]->getPropertyPath());
    }

    private function validUser(): User
    {
        return (new User())
            ->setEmail('role-validation@example.com')
            ->setPseudo('role-validation')
            ->setPassword('test-password');
    }
}
