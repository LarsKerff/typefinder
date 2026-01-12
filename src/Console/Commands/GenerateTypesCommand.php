<?php

namespace Lkrff\TypeFinder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lkrff\TypeFinder\Services\FingerprintService;
use Lkrff\TypeFinder\Services\SandboxDatabaseService;
use Lkrff\TypeFinder\Services\SeederService;
use Lkrff\TypeFinder\Services\TypeScriptGenerator;
use Lkrff\TypeFinder\TypeFinder;

final class GenerateTypesCommand extends Command
{
    protected $signature = 'typefinder:generate
        {--dry-run : Do not write files, only show what would be generated}';

    protected $description = 'Generate TypeScript types from Laravel API Resources using a temporary SQLite database';

    public function handle(
        TypeFinder $typeFinder,
        SandboxDatabaseService $sandbox,
        TypeScriptGenerator $generator,
        FingerprintService $fingerprintService,
        SeederService $seeder,
    ): int {
        $this->info('🔍 Discovering models, resources, and relations…');

        $models = $typeFinder->discover();

        if (empty($models)) {
            $this->warn('No models found.');
            return self::SUCCESS;
        }

        if (! $this->option('dry-run')) {
            $generator->reset();
            // $fingerprintService->reset();
        }

        // Create db and runs migrations
        $sandbox->createSandbox();

        
        try {
      

            $this->info('🧪 Hydrating models and rendering resources…');
            foreach ($models as $model) {
                if (empty($model->table)) {
                    continue;
                }
                
                try {
                $seeder->seed($model);
                } catch(Exception $e) {
                    $this->error('Failed to seed model: ' . $model->modelClass . ' - ' . $e->getMessage());
                    continue;
                }

                $this->info('Seeded: ' . $model->modelClass);
            }
            dd('Stop');
            if (empty($resources)) {
                $this->warn('No resources could be generated.');
                return self::SUCCESS;
            }

            $this->info('');
            $this->info('💾 Generating TypeScript types…');

            foreach ($resources as $res) {
                $generator->generate($res);
            }

            $generator->generateIndexFile();

            $this->info("✅ TypeScript types generated in: {$generator->getOutputPath()}");

        } finally {
            // Reset DB connection
            // DB::setDefaultConnection($originalConnection);
            $this->info('🔄 Restored original database connection.');
        }

        return self::SUCCESS;
    }
}
