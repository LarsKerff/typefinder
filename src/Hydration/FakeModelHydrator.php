<?php

namespace Lkrff\TypeFinder\Hydration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Lkrff\TypeFinder\DTO\ColumnDefinition;
use Lkrff\TypeFinder\DTO\RelationMetadata;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use ReflectionClass;

final class FakeModelHydrator
{
    public function __construct(protected DiscoversSchema $schema) {}

    /**
     * Hydrate a model with fake values and related models/resources.
     */
    public function hydrate(
        Model $model,
        array $columns = [],
        array $relations = [],
        int $depth = 0,
        int $maxDepth = 2
    ): Model {
        // 1️⃣ Hydrate columns
        foreach ($columns as $column) {
            if ($column->nullable) continue;

            $model->setAttribute(
                $column->name,
                $this->fakeValueForColumn($model, $column)
            );
        }

        // 2️⃣ Stop recursion at max depth
        if ($depth >= $maxDepth) return $model;

        // 3️⃣ Hydrate relations
        foreach ($relations as $relation) {
            $relatedClass = $relation->relatedModel;
            if (!$relatedClass || !class_exists($relatedClass)) continue;

            $ref = new ReflectionClass($relatedClass);
            if ($ref->isAbstract()) continue;

            // Fake related model
            $relatedColumns = $this->schema->forModel(new $relatedClass());
            $related = new $relatedClass();
            $related = $this->hydrate($related, $relatedColumns, [], $depth + 1, $maxDepth);

            // Wrap in collection if relation is many
            $value = $this->isCollectionRelation($relation->type)
                ? collect([$related])
                : $related;

            // Wrap in resource if resource class is defined
            if (!empty($relation->resourceClass) && class_exists($relation->resourceClass)) {
                $resourceClass = $relation->resourceClass;

                // ⚡ Skip auth-dependent merges by faking an unauthenticated context
                $value = $value instanceof Collection
                    ? $resourceClass::collection($value)->resolve()
                    : (new $resourceClass($value))->resolve();
            }

            // Set relation on model
            $model->setRelation($relation->name, $value);
        }

        return $model;
    }

    /**
     * Determine if a relation type is a collection.
     */
    private function isCollectionRelation(string $type): bool
    {
        return in_array($type, [
            'HasMany',
            'BelongsToMany',
            'HasManyThrough',
            'MorphMany',
            'MorphToMany',
        ]);
    }

    /**
     * Generate a fake value for a column.
     */
    private function fakeValueForColumn(Model $model, ColumnDefinition $column): mixed
    {
        if ($enumClass = $this->resolveEnumCast($model, $column->name)) {
            return $enumClass::cases()[0] ?? null;
        }

        return match (true) {
            Str::contains($column->type, ['int', 'bigint', 'smallint']) => 1,
            Str::contains($column->type, ['bool', 'tinyint']) => true,
            Str::contains($column->type, ['json', 'jsonb']) => [],
            Str::contains($column->type, ['timestamp', 'datetime', 'date']) => now(),
            Str::contains($column->type, ['char', 'text', 'string', 'varchar']) => 'x',
            Str::contains($column->type, ['float', 'double', 'decimal', 'numeric']) => 1.0,
            default => null,
        };
    }

    /**
     * Resolve enum cast for a column.
     */
    private function resolveEnumCast(Model $model, string $attribute): ?string
    {
        $casts = $model->getCasts();
        if (!isset($casts[$attribute])) return null;
        return enum_exists($casts[$attribute]) ? $casts[$attribute] : null;
    }
}
