<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Lkrff\TypeFinder\Discovery\DiscoveredModel;

final class SandboxDatabaseService
{
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = database_path('typefinder_temp.sqlite');
    }

    /**
     * Create database and run migrations
     */
    public function createSandbox(): void
    {
        $this->bootDatabase();
        $this->runMigrations();
    }

    /**
     * Create & configure sqlite database.
     */
    private function bootDatabase(): void
    {
        if (! file_exists($this->dbPath)) {
            touch($this->dbPath);
        }

        Config::set('database.default', 'typefinder');

        Config::set('database.connections.typefinder', [
            'driver' => 'sqlite',
            'database' => $this->dbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('typefinder');
        DB::reconnect('typefinder');
    }

    /**
     * Run all migrations against the sandbox DB.
     */
    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => 'typefinder',
            '--force' => true,
        ]);
    }

    /**
     * Clean up temp DB if desired.
     */
    public function destroy(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }
}
