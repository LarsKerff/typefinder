<?php

namespace Lkrff\TypeFinder\Console\Commands;

use Illuminate\Console\Command;
use Lkrff\TypeFinder\Services\SandboxDatabaseService;
use Lkrff\TypeFinder\Services\SeederService;
use Lkrff\TypeFinder\Services\TypeScriptGenerator;
use Lkrff\TypeFinder\ModelRegistryBuilder;
use Exception;

final class GenerateTypesCommand extends Command
{
    protected $signature = 'typefinder:generate';

    protected $description = 'Generate TypeScript types from Laravel API Resources using a temporary SQLite database';

    public function handle(
        ModelRegistryBuilder $modelRegistryBuilder,
        SandboxDatabaseService $sandbox,
        SeederService $seeder,
        TypeScriptGenerator $generator
    ): int {
        $this->info('Creating sandbox database and running migrations…');

        // Create temporary SQLite sandbox (migrations only)
        $sandbox->createSandbox();

        $this->info('Discovering models, resources, and relations…');

        // Discover models and register them
        $models = $modelRegistryBuilder->discover();

        if (empty($models)) {
            $this->warn('No models found.');
            return self::SUCCESS;
        }

        $generator->reset();


        // Seed models into the sandbox database and generate TypeScript types
        try {
            $this->info('Hydrating models and seeding database…');

            foreach ($models as $model) {
                try {
                    $seeder->seed($model);
                    $this->info('Seeded: ' . $model->modelClass);
                } catch (Exception $e) {
                    $this->error('Failed to seed model: ' . $model->modelClass . ' - ' . $e->getMessage());
                    continue;
                }
            }

            $this->info('');
            $this->info('Generating TypeScript types…');

            foreach ($models as $model) {
                $generator->generate($model);
            }

            $generator->generateIndexFile();

            $this->info("✅ TypeScript types generated in: {$generator->getOutputPath()}");

        } finally {
            try {
                $sandbox->destroy();
                $this->info('Sandbox destroyed.');
            } catch (Exception $e) {
                $this->error('Failed to destroy sandbox: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
