<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class UserRegistrationValidationTest extends TestCase
{
    public function testAcceptsValidRegistrationIdentity(): void
    {
        $user = (new User())
            ->setEmail('Runner.Example@Email.FR ')
            ->setPseudo('Élodie_42');

        self::assertSame('runner.example@email.fr', $user->getEmail());
        self::assertCount(0, $this->validator()->validateProperty($user, 'email'));
        self::assertCount(0, $this->validator()->validateProperty($user, 'pseudo'));
    }

    #[DataProvider('invalidIdentityProvider')]
    public function testRejectsInvalidRegistrationIdentity(
        string $email,
        string $pseudo,
        string $expectedPath,
    ): void {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo);

        $violations = $this->validator()->validateProperty($user, $expectedPath);
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains($expectedPath, $paths);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidIdentityProvider(): iterable
    {
        yield 'email invalide' => ['not-an-email', 'runner_42', 'email'];
        yield 'email vide' => ['', 'runner_42', 'email'];
        yield 'pseudo trop court' => ['runner@example.com', 'ab', 'pseudo'];
        yield 'pseudo avec espaces' => ['runner@example.com', 'runner name', 'pseudo'];
        yield 'pseudo vide' => ['runner@example.com', '', 'pseudo'];
    }

    private function validator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
