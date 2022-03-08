<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$requiredEnvs = ['OPENLINEAGE_API'];

foreach ($requiredEnvs as $env) {
    if (empty(getenv($env))) {
        throw new Exception(sprintf('The "%s" environment variable is empty.', $env));
    }
}
