<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lkrff\TypeFinder\DTO\DiscoveredModel;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

final class SeederService
{
    public function __construct() {}

    /**
     * Seed exactly ONE model with valid values for every column.
     */
    public function seed(DiscoveredModel $model): ?Model
    {
        $connection = Schema::connection('typefinder');

        if (! $connection->hasTable($model->table)) {
            return null;
        }

        $attributes = [];

        foreach ($model->columns as $column) {
            $attributes[$column->name] = $this->generateValue($column, $model->table);
        }

        $class = $model->modelClass;

        /** @var Model $instance */
        $instance = new $class;
        $instance->forceFill($attributes);
        $instance->save();

        return $instance;
    }

    /**
     * Generate a valid value for a single column based on its metadata.
     */
    private function generateValue(ColumnDefinition $column, string $table): mixed
    {
        // 1️⃣ Enum value
        if ($column->enum) {
            return $column->enum[0]; // pick first enum value
        }

        // 2️⃣ Boolean
        if ($column->boolean) {
            return true;
        }

        // 3️⃣ Numeric range
        if ($column->range) {
            [$min, $max] = $column->range;
            return $min; // just use min value
        }

        $type = strtolower($column->type);

        // 4️⃣ Fallback by type with newline formatting
        $value = match (true) {
            str_contains($type, 'int') => 1,

            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'numeric') => 0.0,

            str_contains($type, 'json') => [
                '_tf' => "$table.{$column->name}",
                'v' => 1,
            ],

            str_contains($type, 'datetime'),
            str_contains($type, 'timestamp') => now()->toDateTimeString(),

            str_contains($type, 'date') => now()->toDateString(),
            str_contains($type, 'time') => now()->toTimeString(),

            str_contains($type, 'text'),
            str_contains($type, 'varchar'),
            str_contains($type, 'char'),
            str_contains($type, 'citext') => ucfirst($column->name),

            default => 'x',
        };

        // Truncate string values if maxLength is defined
        if (is_string($value) && $column->maxLength) {
            $value = substr($value, 0, $column->maxLength);
        }

        return $value;
    }
}
