<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use GuzzleHttp\Client;
use Keboola\SSHTunnel\SSH;
use Psr\Log\LoggerInterface;

class OpenLineageClientFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private Config $config
    ) {
    }

    public function getClient(): Client
    {
        return new Client([
            'base_uri' => $this->getOpenLineageUrl($this->config),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function getOpenLineageUrl(Config $config): string
    {
        $openLineageUrl = $config->getOpenLineageUrl();
        $parameters = $config->getParameters();
        if (isset($parameters['ssh']) && $parameters['ssh']['enabled']) {
            $parsedUrl = (array) parse_url($openLineageUrl);
            $sshTunnel = new SshTunnel(new SSH(), $this->logger);
            $sshTunnel->open($parameters['ssh'], $parsedUrl);

            return sprintf(
                '%s://%s:%s',
                $parsedUrl['scheme'] ?? 'http',
                SshTunnel::LOCAL_HOST,
                SshTunnel::LOCAL_PORT
            );
        }

        return $openLineageUrl;
    }
}
