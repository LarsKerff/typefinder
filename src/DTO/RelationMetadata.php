<?php

namespace Lkrff\TypeFinder\DTO;

final class RelationMetadata
{
    public function __construct(
        public string $name,
        public string $relatedModel,
        public string $type // hasOne, hasMany, belongsTo, etc.
    ) {}
}
