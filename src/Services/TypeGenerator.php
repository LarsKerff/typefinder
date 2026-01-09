<?php

namespace Lkrff\TypeFinder\Services;

use Lkrff\TypeFinder\DTO\Model as DiscoveredModel;
use Lkrff\TypeFinder\DTO\ColumnDefinition;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

final class TypeGenerator
{
    public function __construct(
        protected Filesystem $fs,
        protected string $outputPath = 'resources/js/types', // default output
    ) {}

    /**
     * Generate TypeScript files for all discovered models.
     *
     * @param DiscoveredModel[] $models
     * @param bool $dryRun
     */
    public function generate(array $models, bool $dryRun = false): void
    {
        foreach ($models as $model) {
            $this->generateModel($model, $dryRun);
        }
    }

    protected function generateModel(DiscoveredModel $model, bool $dryRun): void
    {
        $typeName = class_basename($model->model);

        $lines = [];
        $enums = [];

        foreach ($model->columns as $column) {
            $tsType = $this->mapColumnToTs($column);

            // If enum, declare a separate type
            if ($column->type === 'enum' && !empty($column->enumValues)) {
                $enumName = $this->pascalCase($model->model) . $this->pascalCase($column->name);
                $enums[$enumName] = $column->enumValues;
                $lines[] = "  {$column->name}: {$enumName};";
            } else {
                $lines[] = "  {$column->name}: {$tsType};";
            }
        }

        // Build TypeScript content
        $content = '';

        // Export enums first
        foreach ($enums as $enumName => $values) {
            $content .= "export type {$enumName} = " . implode(' | ', array_map(fn($v) => "'$v'", $values)) . ";\n\n";
        }

        // Export interface
        $content .= "export interface {$typeName} {\n";
        $content .= implode("\n", $lines) . "\n";
        $content .= "}\n";

        if ($dryRun) {
            echo "===== {$typeName}.ts =====\n";
            echo $content . "\n";
            return;
        }

        // Ensure output folder
        $folder = $this->outputPath;
        if (! $this->fs->isDirectory($folder)) {
            $this->fs->makeDirectory($folder, 0755, true);
        }

        $filePath = "{$folder}/{$typeName}.ts";
        $this->fs->put($filePath, $content);
    }

    protected function mapColumnToTs(ColumnDefinition $column): string
    {
        if ($column->type === 'enum' && !empty($column->enumValues)) {
            // Will be mapped to a named type, handled in generateModel
            return 'any';
        }

        return match ($column->type) {
                'int', 'integer', 'bigint', 'smallint', 'int8', 'int4',
                'float', 'double', 'decimal', 'numeric', 'real' => 'number',
                'boolean', 'bool' => 'boolean',
                'json', 'jsonb' => 'any',
                'date', 'datetime', 'timestamp' => 'string',
                'varchar', 'char', 'text', 'string' => 'string',
                default => 'any',
            } . ($column->nullable ? ' | null' : '');
    }

    protected function pascalCase(string $value): string
    {
        return Str::studly($value);
    }
}
