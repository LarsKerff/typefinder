<?php

namespace Lkrff\TypeFinder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Lkrff\TypeFinder\TypeFinder;
use Lkrff\TypeFinder\Services\TypeScriptGenerator;
use Lkrff\TypeFinder\Hydration\FakeModelHydrator;

final class GenerateTypesCommand extends Command
{
    protected $signature = 'typefinder:generate
        {--dry-run : Do not write files, only show what would be generated}';

    protected $description = 'Generate TypeScript types from Laravel API Resources';

    public function handle(
        TypeFinder $typeFinder,
        FakeModelHydrator $hydrator,
        TypeScriptGenerator $generator
    ): int {
        $this->info('🔍 Discovering models, resources, and relations…');

        $discovered = $typeFinder->discover();

        if (empty($discovered)) {
            $this->warn('No models with matching API Resources found.');
            return self::SUCCESS;
        }

        // 🔥 Always reset before generating (keeps types in sync)
        if (! $this->option('dry-run')) {
            $generator->reset();
        }

        $this->info('');
        $this->info('🧪 Creating fake models and running resources…');

        $typesData = [];

        foreach ($discovered as $discoveredModel) {
            $this->line('');
            $this->line("• <info>{$discoveredModel->model}</info>");

            $modelClass = $discoveredModel->model;
            $model = new $modelClass();

            // Hydrate model with fake data & relations
            $hydrator->hydrate(
                $model,
                $discoveredModel->columns,
                $discoveredModel->relations
            );

            if (! $discoveredModel->resource) {
                continue;
            }

            $resourceClass = $discoveredModel->resource;
            $resource = new $resourceClass($model);

            // Fully resolved resource array
            $data = $resource->toArray(Request::create('/'));

            $this->line('  Output:');
            $this->line(
                collect($data)
                    ->map(fn ($v, $k) => sprintf('    - %s: %s', $k, get_debug_type($v)))
                    ->implode("\n")
            );

            // Queue for generation
            $typesData[] = [
                'model' => $discoveredModel,
                'data'  => $data,
            ];
        }

        if ($this->option('dry-run')) {
            $this->comment('');
            $this->comment('Dry run enabled — no files written.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('💾 Generating TypeScript types…');

        foreach ($typesData as $item) {
            $generator->generateFromResolved($item['model'], $item['data']);
        }

        // Generate index.ts AFTER all files exist
        $generator->generateIndexFile();

        $this->info("✅ TypeScript types generated in: {$generator->getOutputPath()}");

        return self::SUCCESS;
    }
}
