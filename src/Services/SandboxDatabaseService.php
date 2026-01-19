<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class SandboxDatabaseService
{
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = ':memory:';
    }

    /**
     * Create database and run migrations.
     */
    public function createSandbox(): void
    {
        $this->bootDatabase();
        $this->disableForeignKeys();
        $this->runMigrations();
        $this->truncateAllTables();
    }

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
            'foreign_key_constraints' => false,
        ]);

        DB::purge('typefinder');
        DB::reconnect('typefinder');

        DB::connection('typefinder')->getPdo()->exec('PRAGMA foreign_keys = OFF;');
    }

    private function disableForeignKeys(): void
    {
        DB::connection('typefinder')->getPdo()->exec('PRAGMA foreign_keys = OFF;');
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => 'typefinder',
            '--force' => true,
        ]);
    }

    public function destroy(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    /**
     * Truncate all tables in the sandbox DB to ensure we have an empty DB. (e.g. seeds in migrations)
     */
    private function truncateAllTables(): void
    {
        $connection = DB::connection('typefinder');

        // Get all tables (skip SQLite system tables)
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $connection->table($table->name)->truncate();
        }
    }
}

