<?php

namespace Lkrff\TypeFinder\DTO;

final class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
    ) {}
}
