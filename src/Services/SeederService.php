<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lkrff\TypeFinder\DTO\DiscoveredModel;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

final class SeederService
{
    public function __construct()
    {
    }

    /**
     * Seed TWO rows:
     *  - id = 1 → fully filled
     *  - id = 2 → nullable columns = null
     */
    public function seed(DiscoveredModel $model): void
    {
        $connection = Schema::connection('typefinder');

        if (!$connection->hasTable($model->table)) {
            return;
        }

        $class = $model->modelClass;

        for ($id = 1; $id <= 2; $id++) {
            $makeNullablesNull = $id === 2;

            $attributes = [];

            foreach ($model->columns as $column) {
                $attributes[$column->name] = $this->generateValue(
                    $column,
                    $model->table,
                    $makeNullablesNull,
                    $id
                );
            }

            /** @var Model $instance */
            $instance = new $class;
            $instance->forceFill($attributes);
            $instance->save();
        }
    }

    /**
     * Generate a valid value for a single column based on its metadata.
     */
    private function generateValue(ColumnDefinition $column, string $table, bool $makeNullablesNull = false, int $id = 1): mixed
    {
        if ($makeNullablesNull) {
            if ($column->nullable) {
                return null;
            }
        }

        // Enum value
        if ($column->enum) {
            return $column->enum[$id - 1] ?? $column->enum[0];
        }

        // Boolean value
        if ($column->type === 'tinyint(1)') {
            return 999;
        }                

        // Numeric range
        if ($column->range) {
            [$min, $max] = $column->range;

            return $id === 1 ? $min : ($max ?? $min + 1);
        }


        $type = strtolower($column->type);

        // Fallback by type with newline formatting
        $value = match (true) {
            str_contains($type, 'int') => $id,

            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'numeric') => 1.0,

            str_contains($type, 'json') => [
                '_tf' => "$table.{$column->name}",
                'v' => $id,
            ],

            str_contains($type, 'datetime'),
            str_contains($type, 'timestamp') => now()->toDateTimeString(),

            str_contains($type, 'date') => now()->toDateString(),
            str_contains($type, 'time') => now()->toTimeString(),

            str_contains($type, 'text'),
            str_contains($type, 'varchar'),
            str_contains($type, 'char'),
            str_contains($type, 'citext') => ucfirst($column->name . '_' . $id),

            default => (string) $id,
        };

        // Truncate string values if maxLength is defined
        if (is_string($value) && $column->maxLength) {
            $value = substr($value, 0, $column->maxLength);
        }

        return $value;
    }
}
