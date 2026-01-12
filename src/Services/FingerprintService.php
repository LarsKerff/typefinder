<?php

namespace Lkrff\TypeFinder\Services;

use Lkrff\TypeFinder\DTO\Fingerprint;

final class FingerprintService
{
    /**
     * @var array<string, Fingerprint> keyed by "ModelClass.column"
     */
    private array $fingerprints = [];

    /**
     * Register a fingerprint for a model column.
     * Only keeps one per (modelClass, column), updates nullable if needed.
     */
    public function register(
        string $modelClass,
        string $column,
        bool $nullable,
        mixed $value
    ): void {
        $key = "$modelClass.$column";

        if (!isset($this->fingerprints[$key])) {
            $this->fingerprints[$key] = new Fingerprint(
                modelClass: $modelClass,
                column: $column,
                nullable: $nullable,
                value: $value
            );
        } else {
            // If any occurrence is nullable, mark column as nullable
            if ($nullable && !$this->fingerprints[$key]->nullable) {
                $this->fingerprints[$key]->nullable = true;
            }
        }
    }

    /**
     * Resolve a runtime value back to its column.
     */
    public function resolve(mixed $value): ?Fingerprint
    {
        foreach ($this->fingerprints as $fp) {
            if ($fp->value === $value) {
                return $fp;
            }
        }

        return null;
    }

    /**
     * Was this column ever null?
     */
    public function isNullable(string $modelClass, string $column): bool
    {
        $key = "$modelClass.$column";

        return $this->fingerprints[$key]->nullable ?? false;
    }

    /**
     * Did this column ever exist?
     */
    public function hasColumn(string $modelClass, string $column): bool
    {
        return isset($this->fingerprints["$modelClass.$column"]);
    }

    /**
     * Return all fingerprints as a plain array.
     *
     * @return Fingerprint[]
     */
    public function all(): array
    {
        return array_values($this->fingerprints);
    }

    /**
     * Reset all fingerprints.
     */
    public function reset(): void
    {
        $this->fingerprints = [];
    }
}
