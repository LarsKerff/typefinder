<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Http\Resources\Json\JsonResource;
use Lkrff\TypeFinder\Contracts\DiscoversResources;
use ReflectionClass;

final class DiscoverResources implements DiscoversResources
{
    /**
     * Check if a model has an associated API Resource.
     *
     * @param class-string $modelClass
     */
    public function has(string $modelClass): bool
    {
        return $this->resolve($modelClass) !== null;
    }

    /**
     * Get the API Resource class for a model.
     *
     * @param class-string $modelClass
     * @return class-string<JsonResource>|null
     */
    public function resolve(string $modelClass): ?string
    {
        // By convention: App\Http\Resources\{Model}Resource
        $modelBase = class_basename($modelClass);
        $resourceClass = "App\\Http\\Resources\\{$modelBase}Resource";

        if (! class_exists($resourceClass)) {
            return null;
        }

        if (! is_subclass_of($resourceClass, JsonResource::class)) {
            return null;
        }

        // Optional: verify it can be instantiated
        $reflection = new ReflectionClass($resourceClass);
        if ($reflection->isAbstract()) {
            return null;
        }

        return $resourceClass;
    }
}
