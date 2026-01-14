<?php

namespace Lkrff\TypeFinder;

use Lkrff\TypeFinder\DTO\DiscoveredModel;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

/**
 * Central registry for storing discovered models, columns,
 * and database constraints.
 */
final class TypeRegistry
{
    /**
     * @var array<string, DiscoveredModel>  ['App\Models\User' => DiscoveredModel]
     */
    private array $modelsByClass = [];

    /**
     * @var array<string, DiscoveredModel>  ['users' => DiscoveredModel]
     */
    private array $modelsByTable = [];

    /**
     * Register a discovered model
     */
    public function registerModel(DiscoveredModel $model): void
    {
        $this->modelsByClass[$model->modelClass] = $model;
        $this->modelsByTable[$model->table] = $model;
    }

    /**
     * Get a DiscoveredModel by class name
     */
    public function model(string $class): ?DiscoveredModel
    {
        return $this->modelsByClass[$class] ?? null;
    }

    /**
     * Get a DiscoveredModel by table name
     */
    public function modelForTable(string $table): ?DiscoveredModel
    {
        return $this->modelsByTable[$table] ?? null;
    }

    /**
     * Register a column for a model
     */
    public function registerColumn(string $modelClass, ColumnDefinition $column): void
    {
        $model = $this->model($modelClass);
        if (! $model) {
            return;
        }

        $model->columns[$column->name] = $column;
    }

    /**
     * Get all columns for a model
     *
     * @return ColumnDefinition[]
     */
    public function getColumns(string $modelClass): array
    {
        return $this->model($modelClass)?->columns ?? [];
    }

    /**
     * Get a specific column for a model
     */
    public function getColumn(string $modelClass, string $column): ?ColumnDefinition
    {
        return $this->getColumns($modelClass)[$column] ?? null;
    }

    /**
     * Update enum values for a column
     */
    public function setEnumValues(string $modelClass, string $column, array $enumValues): void
    {
        $col = $this->getColumn($modelClass, $column);
        if ($col) {
            $col->enumValues = $enumValues;
        }
    }

    /**
     * Update numeric constraints
     */
    public function setNumericConstraints(string $modelClass, string $column, ?float $min, ?float $max): void
    {
        $col = $this->getColumn($modelClass, $column);
        if ($col) {
            $col->min = $min;
            $col->max = $max;
        }
    }

    /**
     * Update string length
     */
    public function setStringLength(string $modelClass, string $column, ?int $length): void
    {
        $col = $this->getColumn($modelClass, $column);
        if ($col) {
            $col->maxLength = $length;
        }
    }

    /**
     * Mark column as boolean
     */
    public function setBooleanColumn(string $modelClass, string $column, bool $isBoolean = true): void
    {
        $col = $this->getColumn($modelClass, $column);
        if ($col) {
            $col->isBoolean = $isBoolean;
        }
    }

    /**
     * Get all registered model classes
     *
     * @return string[]
     */
    public function allModels(): array
    {
        return array_keys($this->modelsByClass);
    }

    /**
     * Get table name for a model class
     */
    public function tableForModel(string $modelClass): ?string
    {
        return $this->model($modelClass)?->table;
    }
}
