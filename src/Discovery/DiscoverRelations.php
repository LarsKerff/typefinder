<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lkrff\TypeFinder\Contracts\DiscoversRelations;
use Lkrff\TypeFinder\DTO\RelationMetadata;
use ReflectionClass;

final class DiscoverRelations implements DiscoversRelations
{
    public function forModel(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        $relations = [];

        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods() as $method) {
            // Only public, non-static, no-arg methods
            if (
                !$method->isPublic() ||
                $method->isStatic() ||
                $method->getNumberOfParameters() > 0
            ) {
                continue;
            }

            $name = $method->getName();

            // Skip obvious non-relations
            if (
                $name === '__construct' ||
                str_starts_with($name, 'get') ||
                str_starts_with($name, 'set') ||
                str_starts_with($name, 'scope') ||
                str_starts_with($name, 'boot') ||
                str_starts_with($name, 'new') ||
                str_starts_with($name, 'resolve') ||
                str_starts_with($name, 'to')
            ) {
                continue;
            }

            try {
                $result = $model->$name();

                if ($result instanceof Relation) {
                    $relations[] = new RelationMetadata(
                        name: $name,
                        relatedModel: get_class($result->getRelated()),
                        type: class_basename($result),
                    );
                }
            } catch (\Throwable $e) {
                // Ignore anything that is not a relation
            }
        }

        return $relations;
    }
}
