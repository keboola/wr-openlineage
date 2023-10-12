<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter\Tests;

use DateTimeImmutable;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Keboola\OpenLineageGenerator\GeneratorException;
use Keboola\OpenLineageGenerator\OpenLineageWriter;
use Keboola\OpenLineageWriter\Config;
use Keboola\OpenLineageWriter\ConfigDefinition;
use Keboola\OpenLineageWriter\OpenLineageClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Process\Process;

class OpenLineageWriterTest extends TestCase
{
    public function setUp(): void
    {
        $this->closeSshTunnel();
        parent::setUp();
    }

    private function closeSshTunnel(): void
    {
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }

    private function getQueueClient(array $options): Client
    {
        return new Client(
            array_merge(
                [
                    'base_uri' => 'http://example.com/',
                    'headers' => [
                        'X-StorageApi-Token' => 'token',
                    ],
                ],
                $options,
            ),
        );
    }

    private function getConfig(): Config
    {
        return new Config([
            'parameters' => [
                'openlineage_api_url' => (string) getenv('OPENLINEAGE_API'),
                'created_time_from' => '-1 day',
                'ssh' => [
                    'enabled' => true,
                    '#key_private' => (string) file_get_contents('/root/.ssh/id_rsa'),
                    'ssh_host' => 'sshproxy',
                    'user' => 'root',
                ],
            ],
        ], new ConfigDefinition());
    }

    public function testWrite(): void
    {
        $queueClient = $this->mockQueueClient();
        $testLogger = new TestLogger();

        $config = $this->getConfig();
        $openLineageClientFactory = new OpenLineageClientFactory($testLogger, $config);
        $openLineageClient = $openLineageClientFactory->getClient();

        /** @var Uri $baseUri */
        $baseUri = $openLineageClient->getConfig('base_uri');
        self::assertEquals('http://127.0.0.1:19200', $baseUri);

        $openLineageWriter = new OpenLineageWriter(
            $queueClient,
            $openLineageClient,
            $testLogger,
            new DateTimeImmutable($config->getCreatedTimeFrom()),
            $this->getConfig()->getOpenLineageEndpoint(),
        );

        $openLineageWriter->write();

        $this->assertTrue($testLogger->hasInfoThatContains('Job 123 import to OpenLineage API - start'));
        $this->assertTrue($testLogger->hasInfoThatContains('Job 123 import to OpenLineage API - end'));
        $this->assertTrue($testLogger->hasInfoThatContains('Job 124 import to OpenLineage API - start'));
        $this->assertTrue($testLogger->hasInfoThatContains('Job 124 import to OpenLineage API - end'));

        $expectedNamespace = 'connection.north-europe.azure.keboola.com/project/1234';
        $response = $openLineageClient->get('api/v1/namespaces');
        $responseBody = $this->decodeResponse($response);
        $this->assertEquals($expectedNamespace, $responseBody['namespaces'][0]['name']);

        $response = $openLineageClient->get('api/v1/namespaces/' . urlencode($expectedNamespace) . '/jobs');
        $responseBody = $this->decodeResponse($response);
        $job = $responseBody['jobs'][0];

        $this->assertEquals($expectedNamespace, $job['id']['namespace']);
        $this->assertEquals('keboola.snowflake-transformation-123456', $job['id']['name']);
        $this->assertEquals($expectedNamespace, $job['namespace']);
        $this->assertEquals('keboola.snowflake-transformation-123456', $job['name']);
        $this->assertEquals($expectedNamespace, $job['inputs'][0]['namespace']);
        $this->assertEquals(
            'in.c-kds-team-ex-shoptet-permalink-1234567.orders',
            $job['inputs'][0]['name'],
        );
        $this->assertEquals($expectedNamespace, $job['outputs'][0]['namespace']);
        $this->assertEquals(
            'out.c-orders.dailyStats',
            $job['outputs'][0]['name'],
        );
        $this->assertEquals('keboola.orchestrator-123', $job['latestRun']['facets']['parent']['job']['name']);
    }

    /** @dataProvider lineageErrorHostnameProvider */
    public function testWriteLineageClientException(string $url, string $expectedExceptionMessage): void
    {
        $queueClient = $this->mockQueueClient();

        $testLogger = new TestLogger();

        $config = new Config([
            'parameters' => [
                'openlineage_api_url' => $url,
                'created_time_from' => '-1 day',
            ],
        ], new ConfigDefinition());
        $openLineageClientFactory = new OpenLineageClientFactory($testLogger, $config);
        $openLineageClient = $openLineageClientFactory->getClient([
            'timeout' => 1,
            'connect_timeout' => 1,
        ]);

        $openLineageWriter = new OpenLineageWriter(
            $queueClient,
            $openLineageClient,
            $testLogger,
            new DateTimeImmutable($config->getCreatedTimeFrom()),
            $this->getConfig()->getOpenLineageEndpoint(),
        );

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $openLineageWriter->write();
    }

    public function lineageErrorHostnameProvider(): Generator
    {
        yield 'malformed' => [
            'url' => 'batzilla',
            'expected exception' => 'Malformed URL of OpenLineage server',
        ];

        yield 'unresolved' => [
            'url' => 'http://wrong-api:4567',
            'expected exception' => 'cURL error 6: Could not resolve host: wrong-api',
        ];
    }

    public function testWriteLineageClientServerError(): void
    {
        $queueClient = $this->mockQueueClient();

        $openLineageClient = $this->mockOpenlineageClientError();

        $openLineageWriter = new OpenLineageWriter(
            $queueClient,
            $openLineageClient,
            new NullLogger(),
            new DateTimeImmutable('-7 days'),
            $this->getConfig()->getOpenLineageEndpoint(),
        );

        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Error Communicating with Server');

        $openLineageWriter->write();
    }

    private const JOB_LIST_RESPONSE = '[
        {
            "id": "123",
            "runId": "123",
            "component": "keboola.component",
            "config": "456",
            "result": {
                "input": {
                }
            }
        },
        {
            "id": "124",
            "runId": "124",
            "component": "keboola.component",
            "config": "457",
            "result": {
                "input": {
                }
            }
        }
    ]';

    private const JOB_LINEAGE_RESPONSE = '[
        {
            "eventType": "START",
            "eventTime": "2022-03-04T12:07:00.406Z",
            "run": {
              "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
              "facets": {
                "parent": {
                  "_producer": "https://connection.north-europe.azure.keboola.com",
                  "_schemaURL": "https://openlineage.io/spec/facets/1-0-0/ParentRunFacet.json#/$defs/ParentRunFacet",
                  "run": {
                    "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6"
                  },
                  "job": {
                    "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                    "name": "keboola.orchestrator-123"
                  }
                }
              }
            },
            "job": {
              "namespace": "connection.north-europe.azure.keboola.com/project/1234",
              "name": "keboola.snowflake-transformation-123456"
            },
            "producer": "https://connection.north-europe.azure.keboola.com",
            "inputs": [
              {
                "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                "name": "in.c-kds-team-ex-shoptet-permalink-1234567.orders",
                "facets": {
                  "schema": {
                    "_producer": "https://connection.north-europe.azure.keboola.com",
                    "_schemaURL": "https://openlineage.io/spec/1-0-2/OpenLineage.json#/$defs/InputDatasetFacet",
                    "fields": [
                      {
                        "name": "code"
                      },
                      {
                        "name": "date"
                      },
                      {
                        "name": "totalPriceWithVat"
                      },
                      {
                        "name": "currency"
                      }
                    ]
                  }
                }
              }
            ]
            },
            {
            "eventType": "COMPLETE",
            "eventTime": "2022-03-04T12:07:00.406Z",
            "run": {
              "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6"
            },
            "job": {
              "namespace": "connection.north-europe.azure.keboola.com/project/1234",
              "name": "keboola.snowflake-transformation-123456"
            },
            "producer": "https://connection.north-europe.azure.keboola.com",
            "outputs": [
              {
                "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                "name": "out.c-orders.dailyStats",
                "facets": {
                  "schema": {
                    "_producer": "https://connection.north-europe.azure.keboola.com",
                    "_schemaURL": "https://openlineage.io/spec/1-0-2/OpenLineage.json#/$defs/OutputDatasetFacet",
                    "fields": [
                      {
                        "name": "date"
                      },
                      {
                        "name": "ordersCount"
                      },
                      {
                        "name": "totalPriceEuroSum"
                      }
                    ]
                  }
                }
              }
            ]
            }
        ]';

    private function decodeResponse(ResponseInterface $response): array
    {
        return (array) json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function mockQueueClient(): Client
    {
        $mockHandler = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LIST_RESPONSE,
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LINEAGE_RESPONSE,
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LINEAGE_RESPONSE,
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        return $this->getQueueClient(['handler' => $stack]);
    }

    private function mockOpenlineageClientError(): Client
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('POST', '/api/v1/lineage'),
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);

        return new Client(
            array_merge(
                [
                    'base_uri' => 'http://example.com/',
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'handler' => $stack,
                ],
            ),
        );
    }
}
