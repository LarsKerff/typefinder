<?php

namespace Lkrff\TypeFinder\Services;

use Lkrff\TypeFinder\DTO\Fingerprint;
use Illuminate\Support\Str;

final class FingerprintService
{
    /** @var array<string, Fingerprint> */
    private array $map = [];

    /**
     * Create a new fingerprint for a model column
     */
    public function make(
        string $model,
        string $column,
        string $type,
        bool $nullable
    ): string {
        $value = "TF::{$model}::{$column}::" . Str::uuid();

        $fingerprint = new Fingerprint(
            value: $value,
            model: $model,
            column: $column,
            type: $type,
            nullable: $nullable
        );

        $this->map[$value] = $fingerprint;

        return $value;
    }

    /**
     * Resolve a fingerprint string to the DTO
     */
    public function resolve(mixed $value): ?Fingerprint
    {
        if (is_string($value) && isset($this->map[$value])) {
            return $this->map[$value];
        }

        return null;
    }

    /**
     * Optional: get all fingerprints (useful for debugging)
     */
    public function all(): array
    {
        return $this->map;
    }
}
