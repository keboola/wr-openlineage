<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter;

use Keboola\SSHTunnel\SSH;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;

class SshTunnel
{
    public const LOCAL_HOST = '127.0.0.1';
    public const LOCAL_PORT = '19200';

    private SSH $ssh;
    private LoggerInterface $logger;
    private RetryProxy $retryProxy;
    private string $localPort;

    public function __construct(SSH $ssh, LoggerInterface $logger, string $localPort = self::LOCAL_PORT)
    {
        $this->ssh = $ssh;
        $this->logger = $logger;
        $this->retryProxy = new RetryProxy(new SimpleRetryPolicy(3), new ExponentialBackOffPolicy());
        $this->localPort = $localPort;
    }

    public function open(array $sshParams, array $parsedUrl): void
    {
        $this->logger->info('Creating SSH tunnel to ' . $sshParams['ssh_host']);
        $this->retryProxy->call(
            fn () => $this->ssh->openTunnel([
                'user' => $sshParams['user'],
                'remoteHost' => $parsedUrl['host'],
                'remotePort' => $parsedUrl['port'],
                'localPort' => $this->localPort,
                'sshHost' => $sshParams['ssh_host'],
                'sshPort' => 22,
                'privateKey' => $sshParams['#key_private'],
            ])
        );
    }
}
