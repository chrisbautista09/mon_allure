<?php

namespace App\Tests\Functional;

use App\Command\CreateAdminCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateAdminCommandTest extends KernelTestCase
{
    public function testCommandCreatesAdministratorWithHashedPassword(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $tester = new CommandTester($container->get(CreateAdminCommand::class));
        $tester->setInputs([
            'secure-admin@example.com',
            'secure-admin',
            'VerySecurePass123!',
            'VerySecurePass123!',
        ]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $administrator = $entityManager->getRepository(User::class)->findOneBy([
            'email' => 'secure-admin@example.com',
        ]);
        self::assertInstanceOf(User::class, $administrator);
        self::assertContains('ROLE_ADMIN', $administrator->getRoles());
        self::assertNotSame('VerySecurePass123!', $administrator->getPassword());
        self::assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid(
            $administrator,
            'VerySecurePass123!',
        ));
    }
}
