<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Profile;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
    }

    public function testPhysiologicalValuesWithinBoundsAreValid(): void
    {
        $profile = $this->validProfile();

        self::assertCount(0, $this->validator->validate($profile));
    }

    #[DataProvider('invalidPhysiologicalValueProvider')]
    public function testPhysiologicalValuesOutsideBoundsAreRejected(
        string $property,
        int|float $value
    ): void {
        $profile = $this->validProfile();
        $setter = 'set'.ucfirst($property);
        $profile->{$setter}($value);

        $violations = $this->validator->validateProperty($profile, $property);

        self::assertCount(1, $violations);
    }

    /** @return iterable<string, array{string, int|float}> */
    public static function invalidPhysiologicalValueProvider(): iterable
    {
        yield 'âge inférieur au minimum' => ['age', 17];
        yield 'âge supérieur au maximum' => ['age', 101];
        yield 'VMA inférieure au minimum' => ['vma', 4.9];
        yield 'VMA supérieure au maximum' => ['vma', 30.1];
        yield 'FCM inférieure au minimum' => ['fcm', 99];
        yield 'FCM supérieure au maximum' => ['fcm', 231];
        yield 'FCR inférieure au minimum' => ['fcr', 29];
        yield 'FCR supérieure au maximum' => ['fcr', 121];
    }

    private function validProfile(): Profile
    {
        return (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15.5)
            ->setFcm(190)
            ->setFcr(55)
            ->setUpdatedAt(new \DateTimeImmutable());
    }
}
