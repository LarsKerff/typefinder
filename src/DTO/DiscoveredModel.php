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

    public function column(string $name): ?ColumnDefinition
    {
        return collect($this->columns)->firstWhere('name', $name);
    }

}
