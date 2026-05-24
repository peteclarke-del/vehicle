<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExportZipJobRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:export-zip-job', description: 'Run a queued export ZIP background job')]
class ExportZipJobCommand extends Command
{
    public function __construct(private readonly ExportZipJobRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('jobId', InputArgument::REQUIRED, 'Queued export job id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobId = (string) $input->getArgument('jobId');
        if ($jobId === '') {
            $output->writeln('<error>Missing job id</error>');
            return Command::FAILURE;
        }

        $this->runner->run($jobId);

        return Command::SUCCESS;
    }
}
