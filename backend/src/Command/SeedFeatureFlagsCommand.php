<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\FeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-feature-flags',
    description: 'Seeds the default feature flags into the database'
)]
class SeedFeatureFlagsCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $this->featureFlagService->seedDefaults();

        if ($created > 0) {
            $io->success("Created $created feature flags.");
        } else {
            $io->info('All feature flags already exist. No changes made.');
        }

        return Command::SUCCESS;
    }
}
