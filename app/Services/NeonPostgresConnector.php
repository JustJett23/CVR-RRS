<?php

namespace App\Services;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config): string
    {
        $dsn = parent::getDsn($config);
        $endpoint = $config['neon_endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            return $dsn;
        }

        return $dsn.";options='endpoint={$endpoint}'";
    }
}
