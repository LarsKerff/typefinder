<?php

namespace Lkrff\TypeFinder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Lkrff\TypeFinder\TypeFinder;
use Lkrff\TypeFinder\Services\TypeGenerator;
use Lkrff\TypeFinder\Hydration\FakeModelHydrator;

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

            // 1️⃣ Create empty model
            $modelClass = $discoveredModel->model;
            $model = new $modelClass();

            // 2️⃣ Hydrate with fake data and relations
            $hydrator->hydrate(
                $model,
                $discoveredModel->columns,
                $discoveredModel->relations
            );

            // 3️⃣ Create resource instance if available
            if ($discoveredModel->resource) {
                $resourceClass = $discoveredModel->resource;
                $resource = new $resourceClass($model);

                // 4️⃣ Safely run resource
                $data = $resource->toArray(Request::create('/'));
//                $data = $resource->response()->getData(true);
//                $data = $resource->resolve();

                // 5️⃣ Show output
                $this->line('  Output:');
                $this->line(
                    collect($data)
                        ->map(fn($v, $k) => sprintf(
                            '    - %s: %s',
                            $k,
                            get_debug_type($v)
                        ))
                        ->implode("\n")
                );
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('');
            $this->comment('Dry run enabled — no files written.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('🛠 Type generation will be implemented next.');

        return self::SUCCESS;
    }
}
