<?php

namespace App\Command;

use App\Service\DemoDataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:reset', description: 'Réinitialise uniquement les comptes fictifs de démonstration.')]
final class ResetDemoDataCommand extends Command
{
    public function __construct(private readonly DemoDataService $demoDataService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->demoDataService->reset();
        $io->success(sprintf('Démonstration réinitialisée : %d comptes, %d plans, %d séances et %d performances.', $result['users'], $result['plans'], $result['sessions'], $result['performances']));
        $io->note(sprintf('Sophie peut être inscrite avec %s. Mot de passe des comptes préparés : %s', $result['visitorEmail'], DemoDataService::PASSWORD));

        return Command::SUCCESS;
    }
}
