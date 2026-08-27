<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AlgorithmParameterMappingTest extends KernelTestCase
{
    public function testDoctrineMappingUsesNormalizedNumericParameters(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(AlgorithmParameter::class);

        self::assertSame('algorithm_parameter', $metadata->getTableName());
        self::assertSame(Types::STRING, $metadata->getTypeOfField('parameterKey'));
        self::assertSame(Types::FLOAT, $metadata->getTypeOfField('parameterValue'));
        self::assertSame(Types::TEXT, $metadata->getTypeOfField('description'));
        self::assertSame(Types::DATETIME_IMMUTABLE, $metadata->getTypeOfField('updatedAt'));
        self::assertTrue($metadata->isUniqueField('parameterKey'));
    }
}
