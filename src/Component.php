<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use DateTimeImmutable;
use GuzzleHttp\Client;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\OpenLineageGenerator\GeneratorException;
use Keboola\OpenLineageGenerator\OpenLineageWriter;
use Keboola\StorageApi\Client as StorageClient;
use Throwable;

class Component extends BaseComponent
{
    protected function run(): void
    {
        $storageUrl = (string) getenv('KBC_URL');
        $storageToken = (string) getenv('KBC_TOKEN');

        /** @var Config $config */
        $config = $this->getConfig();

        $storageClient = new StorageClient([
            'token' => $storageToken,
            'url' => $storageUrl,
        ]);

        $queueClient = new Client([
            'base_uri' => $storageClient->getServiceUrl('queue'),
            'headers' => [
                'X-StorageApi-Token' => $storageToken,
            ],
        ]);

        $openLineageClientFactory = new OpenLineageClientFactory($this->getLogger(), $config);
        $openLineageClient = $openLineageClientFactory->getClient();

        try {
            $createdTimeFrom = new DateTimeImmutable($config->getCreatedTimeFrom());
        } catch (Throwable $e) {
            throw new UserException(sprintf(
                'Unable to parse "created_time_from": %s',
                $e->getMessage(),
            ));
        }

        $openLineageWriter = new OpenLineageWriter(
            $queueClient,
            $openLineageClient,
            $this->getLogger(),
            $createdTimeFrom,
            $config->getOpenLineageEndpoint(),
            $config->getJobNameAsConfig(),
        );

        try {
            $openLineageWriter->write();
        } catch (GeneratorException $e) {
            throw new UserException($e->getMessage());
        }
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
