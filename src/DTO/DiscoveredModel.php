<?php

namespace Lkrff\TypeFinder\DTO;

final class DiscoveredModel
{
    public function __construct(
        public string $modelClass,
        public string $table,
        public array $columns = [],
        public array $relations = [],
        public ?string $resourceClass = null,
//        public ?array $resourceTree = null
    ) {}
}
