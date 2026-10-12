<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vercel:migrate')]
#[Description('Run database migrations for PostgreSQL Vercel deployments')]
class VercelMigrateDatabase extends Command
{
    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->info('PostgreSQL is not the configured database; no deployment migration was run.');

            return self::SUCCESS;
        }

        return $this->call('migrate', ['--force' => true]);
    }
}
