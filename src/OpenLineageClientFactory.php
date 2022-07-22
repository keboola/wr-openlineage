<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use GuzzleHttp\Client;
use Keboola\Component\UserException;
use Keboola\SSHTunnel\SSH;
use Keboola\SSHTunnel\SSHException;
use Psr\Log\LoggerInterface;
use Retry\RetryException;

class OpenLineageClientFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private Config $config
    ) {
    }

    public function getClient(array $options = []): Client
    {
        return new Client(array_merge(
            [
                'base_uri' => $this->getOpenLineageUrl($this->config),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
            $options
        ));
    }

    private function getOpenLineageUrl(Config $config): string
    {
        $openLineageUrl = $config->getOpenLineageUrl();
        $parameters = $config->getParameters();
        if (isset($parameters['ssh']) && $parameters['ssh']['enabled']) {
            $parsedUrl = (array) parse_url($openLineageUrl);
            try {
                $sshTunnel = new SshTunnel(new SSH(), $this->logger);
                $sshTunnel->open($parameters['ssh'], $parsedUrl);
            } catch (SSHException | RetryException $e) {
                throw new UserException('Unable to open SSH tunnel');
            }

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
