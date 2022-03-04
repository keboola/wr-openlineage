<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use DateTimeImmutable;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class OpenLineageWriter
{
    public function __construct(
        private Client $queueClient,
        private Client $openLineageClient,
        private LoggerInterface $logger,
        private DateTimeImmutable $createdTimeFrom,
        private bool $jobNameAsConfig = false
    ) {
    }

    public function write(): void
    {
        $jobsResponse = $this->getJobs();

        foreach (array_reverse($jobsResponse) as $job) {
            if (!isset($job['result']['input'])) {
                $this->logger->info(
                    sprintf('Skipping older job "%s" without I/O in the result.', $job['id'])
                );
                continue;
            }
            $this->logger->info(sprintf('Job %s import to OpenLineage API - start', $job['id']));

            $lineAgeResponse = $this->getJobLineage($job['id']);
            foreach ($lineAgeResponse as $event) {
                if ($this->jobNameAsConfig) {
                    $event['job']['name'] = sprintf('%s-%s', $job['component'], $job['config']);
                }
                $this->logger->info(sprintf('- Sending %s event', $event['eventType']));
                $this->openLineageClient->request('POST', '/api/v1/lineage', [
                    'body' => json_encode($event),
                ]);
            }

            $this->logger->info(sprintf('Job %s export to Marquez - end', $job['id']));
        }

        $this->logger->info('Done');
    }

    private function getJobs(): array
    {
        $response = $this->queueClient->request(
            'GET',
            sprintf(
                '/jobs?%s',
                http_build_query([
                    'createdTimeFrom' => $this->createdTimeFrom->format('c'),
                    'sortOrder' => 'desc',
                    'status' => 'success',
                ])
            )
        );
        return $this->decodeResponse($response);
    }

    private function getJobLineage(string $jobId): array
    {
        $response = $this->queueClient->request(
            'GET',
            sprintf('/jobs/%s/open-api-lineage', $jobId)
        );

        return $this->decodeResponse($response);
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        return (array) json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }
}
