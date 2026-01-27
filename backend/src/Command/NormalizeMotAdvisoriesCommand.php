<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MotRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:mot:normalize-advisories', description: 'Normalize MOT advisory and failure items into text entries')]
final class NormalizeMotAdvisoriesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(MotRecord::class);
        $records = $repo->findAll();
        $count = 0;
        foreach ($records as $mot) {
            if (!$mot instanceof MotRecord) {
                continue;
            }

            $changed = false;

            $adv = $mot->getAdvisoryItems();
            if (is_array($adv)) {
                $mapped = array_map(function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }
                    if (is_array($item)) {
                        return $item['text'] ?? json_encode($item);
                    }
                    // fallback
                    return (string)$item;
                }, $adv);
                if (json_encode($mapped) !== json_encode($adv)) {
                    $mot->setAdvisoryItems($mapped);
                    $changed = true;
                }
            } elseif (!empty($mot->getAdvisories())) {
                // If advisories stored as newline text, leave as-is
            }

            $fail = $mot->getFailureItems();
            if (is_array($fail)) {
                $mapped = array_map(function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }
                    if (is_array($item)) {
                        return $item['text'] ?? json_encode($item);
                    }
                    return (string)$item;
                }, $fail);
                if (json_encode($mapped) !== json_encode($fail)) {
                    $mot->setFailureItems($mapped);
                    $changed = true;
                }
            }

            if ($changed) {
                $this->em->persist($mot);
                $count++;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('Normalized %d MOT records', $count));

        return Command::SUCCESS;
    }
}
