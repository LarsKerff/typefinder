<?php

namespace Lkrff\TypeFinder\Console\Commands;

use Illuminate\Console\Command;
use Lkrff\TypeFinder\TypeFinder;
use Lkrff\TypeFinder\Services\TypeGenerator;
use Lkrff\TypeFinder\Hydration\FakeModelHydrator;

use Illuminate\Http\Request;

final class GenerateTypesCommand extends Command
{
    protected $signature = 'typefinder:generate
        {--dry-run : Do not write files, only show what would be generated}';

    protected $description = 'Generate TypeScript types from Laravel API Resources';

    public function handle(
        TypeFinder $typeFinder,
        FakeModelHydrator $hydrator,
        TypeGenerator $generator
    ): int {
        $this->info('🔍 Discovering models, resources, and relations…');

        $discovered = $typeFinder->discover();

        if (empty($discovered)) {
            $this->warn('No models with matching API Resources found.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('🧪 Creating fake models and running resources…');

        foreach ($discovered as $discoveredModel) {
            $this->line('');
            $this->line("• <info>{$discoveredModel->model}</info>");

            $modelClass = $discoveredModel->model;
            $model = new $modelClass();

            // Hydrate model with fake data and relations
            $hydrator->hydrate(
                $model,
                $discoveredModel->columns,
                $discoveredModel->relations
            );

            if ($discoveredModel->resource) {
                $resourceClass = $discoveredModel->resource;
                $resource = new $resourceClass($model);

                // Fully resolve resource to plain PHP array
                $data = ResourceResolver::resolve($resource);

                // Show output
                $this->line('  Output:');
                $this->line(
                    collect($data)
                        ->map(fn($v, $k) => sprintf(
                            '    - %s: %s',
                            $k,
                            is_array($v) ? 'array' : get_debug_type($v)
                        ))
                        ->implode("\n")
                );

                // Only generate TypeScript if not dry-run
                if (!$this->option('dry-run')) {
                    $generator->generateResource($discoveredModel->model, $data);
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('');
            $this->comment('Dry run enabled — no files written.');
        } else {
            $this->info('');
            $this->info('🛠 TypeScript types generated successfully.');
        }

        return self::SUCCESS;
    }
}
