<?php

namespace Lkrff\TypeFinder\DTO;

final class Fingerprint
{
    public function __construct(
        public string $value,
        public string $model,
        public string $column,
        public string $type,
        public bool $nullable,
    ) {}
}
