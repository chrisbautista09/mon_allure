<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur depuis le serveur.',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création d’un administrateur Mon Allure');

        $email = mb_strtolower(trim((string) $io->ask('Adresse e-mail')));
        $pseudo = trim((string) $io->ask('Pseudo'));

        if ($this->userRepository->findOneBy(['email' => $email]) instanceof User) {
            $io->error('Un compte utilise déjà cette adresse e-mail.');

            return Command::FAILURE;
        }

        if ($this->userRepository->findOneBy(['pseudo' => $pseudo]) instanceof User) {
            $io->error('Ce pseudo est déjà utilisé.');

            return Command::FAILURE;
        }

        $password = (string) $io->askHidden('Mot de passe');
        $confirmation = (string) $io->askHidden('Confirmez le mot de passe');

        if ($password !== $confirmation) {
            $io->error('Les mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        if (mb_strlen($password) < 12) {
            $io->error('Le mot de passe doit contenir au moins 12 caractères.');

            return Command::FAILURE;
        }

        $administrator = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setRoles(['ROLE_ADMIN']);
        $administrator->setPassword($this->passwordHasher->hashPassword($administrator, $password));

        $violations = $this->validator->validate($administrator);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getMessage());
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($administrator);
        $this->entityManager->flush();

        $io->success(sprintf('Le compte administrateur %s a été créé.', $administrator->getEmail()));

        return Command::SUCCESS;
    }
}
