<?php

namespace Lkrff\TypeFinder\DTO;

final class ColumnDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly ?array $enum = null,
        public readonly bool $boolean = false,
        public readonly ?array $range = null,       // [min, max] if numeric
        public readonly ?int $maxLength = null
    ) {}
}
