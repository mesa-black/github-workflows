<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:workflow:delete',
    description: 'Delete all workflow with date superior to the current date + 15 days',
)]
final class WorkflowDeleteCommand extends Command
{
    private const GITHUB_API = 'https://api.github.com';
    private const MAX_DAYS = 15;

    public function __construct(
        private readonly string $githubToken,
        private readonly string $githubOrganization,
        private readonly string $githubRepository,
        private readonly HttpClientInterface $client,
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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
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
            foreach ($this->getWorkflows($page, $pages)['workflow_runs'] as $workflow) {
                $created = new \DateTime($workflow['created_at']);
                $diff = $created->diff(new \DateTime())->days;

                if ($diff > self::MAX_DAYS) {
                    if ($input->getOption('dry-run') !== true) {
                        if ($this->deleteWorkflow($workflow['id'])) {
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

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function getWorkflows(int $page, int &$pages): array
    {
        $response = $this->client->request(
            'GET',
            self::GITHUB_API.'/repos/'.$this->githubOrganization.'/'.$this->githubRepository.'/actions/runs',
            [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
                'auth_bearer' => $this->githubToken,
                'query' => [
                    'status' => 'completed',
                    'per_page' => 100,
                    'page' => $page,
                ],
            ]
        );

        if (isset($response->getHeaders()['link'])) {
            $matches = [];
            preg_match('/(&|\?)page=([0-9]+)>; rel="last"/i', $response->getHeaders()['link'][0], $matches);

            if (isset($matches[2])) {
                $pages = (int) $matches[2];
            }
        }

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function deleteWorkflow(int $workflowId): bool
    {
        $response = $this->client->request(
            'DELETE',
            self::GITHUB_API.'/repos/'.$this->githubOrganization.'/'.$this->githubRepository.'/actions/runs/'.$workflowId,
            [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
                'auth_bearer' => $this->githubToken,
            ]
        );

        if (204 === $response->getStatusCode()) {
            return true;
        }

        return false;
    }
}
