<?php

namespace App\Command;

use App\Repository\CommandeRepository;
use App\Service\StatsMongoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:stats:sync', description: 'Reconstruit les statistiques MongoDB à partir des commandes MySQL')]
class SyncStatsMongoCommand extends Command
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private StatsMongoService $statsMongo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $nombre = $this->statsMongo->reconstruire($this->commandeRepository->findAll());
        } catch (\Throwable $e) {
            $io->error('Synchronisation impossible : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success($nombre . ' commande(s) copiée(s) dans MongoDB.');

        return Command::SUCCESS;
    }
}
