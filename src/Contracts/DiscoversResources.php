<?php

namespace Lkrff\TypeFinder\Contracts;

interface DiscoversResources
{
    /**
     * Check if a model has an API Resource.
     *
     * @param class-string $modelClass
     */
    public function has(string $modelClass): bool;

    /**
     * Resolve the API Resource class for a model.
     *
     * @param class-string $modelClass
     * @return class-string|null
     */
    public function resolve(string $modelClass): ?string;
}
