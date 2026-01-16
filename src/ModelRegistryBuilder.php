<?php

namespace Lkrff\TypeFinder;

use Lkrff\TypeFinder\Contracts\DiscoversModels;
use Lkrff\TypeFinder\Contracts\DiscoversRelations;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use Lkrff\TypeFinder\DTO\DiscoveredModel;

final class ModelRegistryBuilder
{
    public function __construct(
        protected DiscoversModels $models,
        protected DiscoversSchema $schema,
        protected DiscoversRelations $relations
    ) {}

    /**
     * @return DiscoveredModel[]
     */
    public function discover(): array
    {
        return collect($this->models->all())
            ->map(fn (string $modelClass) => $this->enrichModel($modelClass))
            ->values()
            ->all();
    }

    protected function enrichModel(string $modelClass): DiscoveredModel
    {
        $model = new $modelClass();
        $table = $model->getTable();

        return new DiscoveredModel(
            modelClass: $modelClass,
            table: $table,
            columns: $this->schema->forModel($model),
            relations: $this->relations->forModel($modelClass),
            resourceClass: $this->resolveResourceClass($modelClass)
        );
    }


    /**
     * Resolve the Resource class for a model without instantiating it.
     */
    protected function resolveResourceClass(string $modelClass): ?string
    {
        // Only models using TransformsToResource have this method
        if (! method_exists($modelClass, 'guessResourceName')) {
            return null;
        }

        foreach ($modelClass::guessResourceName() as $candidate) {
            if (is_string($candidate) && class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
