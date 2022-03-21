<?php

declare(strict_types=1);

namespace Keboola\OpenLineageWriter\Tests;

use Generator;
use Keboola\OpenLineageWriter\ConfigDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigDefinitionTest extends TestCase
{
    public function validConfigurationData(): Generator
    {
        yield 'minimal configuration' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                ],
            ],
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => false,
                ],
            ],
        ];

        yield 'configuration with job names' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'localhost:3000',
                    'created_time_from' => '-2 day',
                    'job_name_as_config' => true,
                ],
            ],
            [
                'parameters' => [
                    'openlineage_api_url' => 'localhost:3000',
                    'created_time_from' => '-2 day',
                    'job_name_as_config' => true,
                ],
            ],
        ];

        yield 'configuration with enabled ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => true,
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                        'user' => 'keboola',
                    ],
                ],
            ],
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => true,
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                        'user' => 'keboola',
                    ],
                ],
            ],
        ];

        yield 'configuration with disabled ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => true,
                    'ssh' => [
                        'enabled' => false,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                        'user' => 'keboola',
                    ],
                ],
            ],
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => true,
                    'ssh' => [
                        'enabled' => false,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                        'user' => 'keboola',
                    ],
                ],
            ],
        ];
    }

    public function invalidConfigurationData(): Generator
    {
        yield 'empty parameters' => [
            [
                'parameters' => [],
            ],
            'The child config "openlineage_api_url" under "root.parameters" must be configured.',
        ];

        yield 'empty openlineage_api_url' => [
            [
                'parameters' => [
                    'openlineage_api_url' => null,
                ],
            ],
            'The path "root.parameters.openlineage_api_url" cannot contain an empty value, but got null.',
        ];

        yield 'missing created_time_from' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'localhost',
                ],
            ],
            'The child config "created_time_from" under "root.parameters" must be configured.',
        ];

        yield 'empty created_time_from' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'localhost',
                    'created_time_from' => null,
                ],
            ],
            'The path "root.parameters.created_time_from" cannot contain an empty value, but got null.',
        ];

        yield 'invalid job_name_as_config' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'localhost',
                    'created_time_from' => '-1 day',
                    'job_name_as_config' => '123',
                ],
            ],
            'Invalid type for path "root.parameters.job_name_as_config". Expected "bool", but got "string".',
        ];

        yield 'missing enabled under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [],
                ],
            ],
            'The child config "enabled" under "root.parameters.ssh" must be configured.',
        ];

        yield 'invalid enabled under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => 123,
                    ],
                ],
            ],
            'Invalid type for path "root.parameters.ssh.enabled". Expected "bool", but got "int".',
        ];

        yield 'missing #key_private under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'The child config "#key_private" under "root.parameters.ssh" must be configured.',
        ];

        yield 'empty #key_private under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => null,
                    ],
                ],
            ],
            'The path "root.parameters.ssh.#key_private" cannot contain an empty value, but got null.',
        ];

        yield 'missing ssh_host under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                    ],
                ],
            ],
            'The child config "ssh_host" under "root.parameters.ssh" must be configured.',
        ];

        yield 'empty ssh_host under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => null,
                    ],
                ],
            ],
            'The path "root.parameters.ssh.ssh_host" cannot contain an empty value, but got null.',
        ];

        yield 'missing user under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                    ],
                ],
            ],
            'The child config "user" under "root.parameters.ssh" must be configured.',
        ];

        yield 'empty user under ssh' => [
            [
                'parameters' => [
                    'openlineage_api_url' => 'https://localhost',
                    'created_time_from' => '-1 day',
                    'ssh' => [
                        'enabled' => true,
                        '#key_private' => '-----BEGIN RSA PRIVATE KEY-----\n-----END RSA PRIVATE KEY-----\n',
                        'ssh_host' => '127.0.0.1',
                        'user' => null,
                    ],
                ],
            ],
            'The path "root.parameters.ssh.user" cannot contain an empty value, but got null.',
        ];
    }

    /**
     * @dataProvider validConfigurationData
     */
    public function testValidConfiguration(array $inputConfig, array $expectedConfig): void
    {
        $processor = new Processor();
        $processedConfig = $processor->processConfiguration(new ConfigDefinition(), [$inputConfig]);
        self::assertSame($expectedConfig, $processedConfig);
    }

    /**
     * @dataProvider invalidConfigurationData
     */
    public function testInvalidConfiguration(array $inputConfig, string $expectedExceptionMessage): void
    {
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $processor->processConfiguration(new ConfigDefinition(), [$inputConfig]);
    }
}
