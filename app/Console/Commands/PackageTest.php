<?php

namespace App\Console\Commands;

use Illuminate\Http\Client\PendingRequest;
use InstallerToolkit\PackageTestCommand;
use RuntimeException;

/**
 * Runs a built package's installer end to end, then checks the installed app.
 */
class PackageTest extends PackageTestCommand
{
    /**
     * Confirm the installed application reached its database and cache.
     *
     * /health resolves both, so it fails where a page served from a broken
     * install might still render. Its queue check is ignored: a fresh install
     * has run no job yet, which reports "unknown", not a fault.
     */
    protected function assertAppSpecificFunctionality(PendingRequest $client, string $baseUri): void
    {
        $response = $client->get($baseUri.'/health');

        if ($response->json('checks.database.status') !== 'ok' || $response->json('checks.cache.status') !== 'ok') {
            throw new RuntimeException("Expected /health to report the database and cache ok, got {$response->status()}: {$response->body()}");
        }
    }
}
