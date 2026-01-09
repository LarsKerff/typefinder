<?php

namespace Lkrff\TypeFinder;

use Lkrff\TypeFinder\Contracts\DiscoversModels;
use Lkrff\TypeFinder\Contracts\DiscoversRelations;
use Lkrff\TypeFinder\Contracts\DiscoversResources;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use Lkrff\TypeFinder\DTO\Model as DiscoveredModel;
use Lkrff\TypeFinder\DTO\RelationMetadata;
use Illuminate\Database\Eloquent\Relations\Relation;

final class TypeFinder
{
    public function __construct(
        protected DiscoversModels $models,
        protected DiscoversResources $resources,
        protected DiscoversSchema $schema,
        protected DiscoversRelations $relations,
    ) {}

    /**
     * Discover all models along with table, columns, optional resource, and relations.
     *
     * @return DiscoveredModel[]
     */
    public function discover(): array
    {
        return collect($this->models->all())
            ->map(fn(string $modelClass) => $this->enrichModel($modelClass))
            ->values()
            ->all();
    }

    /**
     * Enrich a model with columns, optional resource, and relations.
     *
     * @param class-string $modelClass
     * @return DiscoveredModel
     */
    protected function enrichModel(string $modelClass): DiscoveredModel
    {
        $model = new $modelClass();

        return new DiscoveredModel(
            model: $modelClass,
            table: $model->getTable(),
            columns: $this->schema->forModel($model),
            resource: $this->resources->has($modelClass)
                ? $this->resources->resolve($modelClass)
                : null,
            relations: $this->relations->forModel($modelClass),
        );
    }
}
