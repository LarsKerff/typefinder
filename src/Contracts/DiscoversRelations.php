<?php

namespace Lkrff\TypeFinder\Contracts;

use Lkrff\TypeFinder\DTO\RelationMetadata;

interface DiscoversRelations
{
    /**
     * Discover all relations for a given model class.
     *
     * @param class-string $modelClass
     * @return RelationMetadata[]
     */
    public function forModel(string $modelClass): array;
}
