<?php

namespace Lkrff\TypeFinder\DTO;

final class Model
{
    public function __construct(
        public string $model,
        public string $table,
        public array $columns = [],
        public ?string $resource = null,
        public array $relations = [], // new: relationName => RelationMetadata
    ) {}
}
