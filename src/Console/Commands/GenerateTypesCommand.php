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
    protected $signature = 'typefinder:generate
        {--dry-run : Do not write files, only show what would be generated}';

    protected $description = 'Generate TypeScript types from Laravel API Resources using a temporary SQLite database';

    public function handle(
        ModelRegistryBuilder $modelRegistryBuilder,
        SandboxDatabaseService $sandbox,
        SeederService $seeder,
        TypeScriptGenerator $generator
    ): int {
        $this->info('🔍 Creating sandbox database and running migrations…');

        // 1️⃣ Create temporary SQLite sandbox (migrations only)
        $sandbox->createSandbox();

        $this->info('🔍 Discovering models, resources, and relations…');

        // 2️⃣ Discover models and register them
        $models = $modelRegistryBuilder->discover();

        if (empty($models)) {
            $this->warn('No models found.');
            return self::SUCCESS;
        }

        if (! $this->option('dry-run')) {
            $generator->reset();
        }

        try {
            $this->info('🧪 Hydrating models and seeding database…');

            foreach ($models as $model) {
                try {
                    $seeder->seed($model);
                    $this->info('Seeded: ' . $model->modelClass);
                } catch (Exception $e) {
                    $this->error('Failed to seed model: ' . $model->modelClass . ' - ' . $e->getMessage());
                    continue;
                }
            }
//            dd($models[16]);
            $this->info('');
            $this->info('💾 Generating TypeScript types…');
//
//            foreach ($models as $model) {
//                if ($model->resourceClass) {
//                    $generator->generate($model->resourceClass);
//                }
//            }

            $generator->generateIndexFile();

            $this->info("✅ TypeScript types generated in: {$generator->getOutputPath()}");

        } finally {
            $sandbox->destroy();
            $this->info('🔄 Sandbox database destroyed and original database connection restored.');
        }

        return self::SUCCESS;
    }
}
