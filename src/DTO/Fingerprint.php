<?php

namespace Lkrff\TypeFinder\DTO;

final class Fingerprint
{
    public function __construct(
        public string $modelClass,
        public string $column,
        public bool $nullable,
        public mixed $value
    ) {}
}
