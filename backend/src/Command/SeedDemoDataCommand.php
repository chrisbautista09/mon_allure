<?php

namespace App\Command;

use App\Service\DemoDataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:seed', description: 'Crée les personas et données de démonstration Mon Allure.')]
final class SeedDemoDataCommand extends Command
{
    public function __construct(private readonly DemoDataService $demoDataService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $result = $this->demoDataService->seed();
        } catch (\LogicException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->displaySummary($io, $result);

        return Command::SUCCESS;
    }

    /** @param array{users: int, plans: int, sessions: int, performances: int, visitorEmail: string} $result */
    private function displaySummary(SymfonyStyle $io, array $result): void
    {
        $io->success(sprintf('%d comptes, %d plans, %d séances et %d performances créés.', $result['users'], $result['plans'], $result['sessions'], $result['performances']));
        $io->table(['Compte', 'Identifiant'], [
            ['Karim', 'karim@demo.mon-allure.fr'],
            ['Julien', 'julien@demo.mon-allure.fr'],
            ['Élodie', 'elodie@demo.mon-allure.fr'],
            ['Administrateur', 'admin@demo.mon-allure.fr'],
            ['Sophie - adresse libre pour la vidéo d’inscription', $result['visitorEmail']],
        ]);
        $io->note('Mot de passe commun : '.DemoDataService::PASSWORD);
    }
}
