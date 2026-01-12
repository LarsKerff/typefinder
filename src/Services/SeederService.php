<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Database\Eloquent\Model;
use Lkrff\TypeFinder\DTO\DiscoveredModel;
use Illuminate\Support\Facades\Schema;

final class SeederService
{
    public function __construct(
        private FingerprintService $fingerprints
    ) {}

    /**
     * Seed exactly ONE model with fingerprints for every column.
     */
    public function seed(DiscoveredModel $model): ?Model
    {
        $class = $model->modelClass;
        $table = $model->table;

        // Skip if table doesn't exist
        if (! Schema::connection('typefinder')->hasTable($table)) {
            return null;
        }

        $attributes = [];

        // Fill every column with a fingerprint
        foreach ($model->columns as $column) {
            $fingerprint = $this->fingerprints->make(
                $class,
                $column->name,
                $column->type,
                $column->nullable
            );

            $attributes[$column->name] = $this->castValue($fingerprint, $column->type);
        }

        // Create the model row directly
        /** @var Model $instance */
        $instance = $class::create($attributes);

        return $instance;
    }

    /**
     * Convert fingerprint string into a valid DB value
     */
    private function castValue(string $fingerprint, string $dbType): mixed
    {
        $hash = abs(crc32($fingerprint));

        return match (true) {
            str_contains($dbType, 'int') => $hash % 10000, // smaller ints 0–9999
            str_contains($dbType, 'bool') => true,
            str_contains($dbType, 'float'),
            str_contains($dbType, 'double'),
            str_contains($dbType, 'numeric') => ($hash % 10000) / 10, // 0.0–999.9
            default => $fingerprint,
        };
    }
}
