<?php

namespace App\Command;

use App\Entity\InsurancePolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate:policies-to-holders', description: 'Migrate insurance policies to reference holder Insurance rows (dry-run by default).')]
class MigratePoliciesToHoldersCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply changes (persist and flush)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of policies to process (for testing)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool)$input->getOption('apply');
        $limit = $input->getOption('limit');

        // This command was used for legacy migrations and is now deprecated.
        $io->warning('This command is deprecated; migration logic removed.');
        return Command::SUCCESS;
        
    }
}
