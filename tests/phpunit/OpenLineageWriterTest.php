<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter\Tests;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\OpenLineageWriter\OpenLineageWriter;
use Keboola\StorageApi\Client as StorageClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class OpenLineageWriterTest extends TestCase
{
    private function getQueueClient(string $storageToken, array $options): Client
    {
        return new Client(
            array_merge(
                [
                    'base_uri' => 'http://example.com/',
                    'headers' => [
                        'X-StorageApi-Token' => $storageToken,
                    ],
                ],
                $options
            )
        );
    }

    public function testWrite(): void
    {
        $storageUrl = (string) getenv('KBC_URL');
        $storageToken = (string) getenv('KBC_TOKEN');

        $storageClient = new StorageClient([
            'token' => $storageToken,
            'url' => $storageUrl,
        ]);

        $mockHandler = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LIST_RESPONSE
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LINEAGE_RESPONSE
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);
        $queueClient = $this->getQueueClient($storageToken, ['handler' => $stack]);

        $openLineageClient = new Client([
            'base_uri' => (string) getenv('OPENLINEAGE_API'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $createdTimeFrom = new DateTimeImmutable('-1 day');

        $testLogger = new TestLogger();
        $openLineageWriter = new OpenLineageWriter(
            $queueClient,
            $openLineageClient,
            $testLogger,
            $createdTimeFrom
        );

        $openLineageWriter->write();

        var_dump($testLogger->records);
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
                "name": "out.c-orders.dailyStats\"",
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
}
