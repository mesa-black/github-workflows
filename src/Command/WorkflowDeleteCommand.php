<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WorkflowManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:workflow:delete',
    description: 'Delete all workflow with date superior to the current date + 15 days',
)]
final class WorkflowDeleteCommand extends Command
{
    private const MAX_DAYS = 15;

    public function __construct(
        private readonly WorkflowManagerInterface $workflowManager,
    ) {
        parent::__construct();
    }
    public function configure(): void
    {
        $this
            ->addOption('dry-run', null, null, 'Dry run')
        ;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run') === true) {
            $io->warning('This is a dry run, no changes will be made.');
        }

        $pages = 0;
        $totalProcessedWorkflows = 0;
        $page = 1;

        $io->info('Processing workflows...');

        while ($pages === 0 || $page <= $pages) {
            foreach ($this->workflowManager->getWorkflows($page, $pages)['workflow_runs'] as $workflow) {
                $created = new \DateTime($workflow['created_at']);
                $diff = $created->diff(new \DateTime())->days;

                if ($diff > self::MAX_DAYS) {
                    if ($input->getOption('dry-run') !== true) {
                        if ($this->workflowManager->deleteWorkflow($workflow['id'])) {
                            ++$totalProcessedWorkflows;
                        }
                    } else {
                        ++$totalProcessedWorkflows;
                    }
                }
            }

            ++$page;
        }

        if ($input->getOption('dry-run') === true) {
            $io->success(sprintf('%d workflows would have been deleted', $totalProcessedWorkflows));
        } else {
            $io->success(sprintf('%d workflows deleted successfully', $totalProcessedWorkflows));
        }

        return Command::SUCCESS;
    }
}
