<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class WorkflowManagerManager implements WorkflowManagerInterface
{
    private const GITHUB_API = 'https://api.github.com';

    public function __construct(
        #[\SensitiveParameter]
        private string $githubToken,
        private string $githubOrganization,
        private string $githubRepository,
        private HttpClientInterface $client,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getWorkflows(int $page, int &$pages): array
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
    public function deleteWorkflow(int $workflowId): bool
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
