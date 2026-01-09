<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Support\Str;

final class TypeScriptGenerator
{
    protected string $resourcePath;
    protected string $outputPath;
    protected string $namespace;

    public function __construct()
    {
        $this->resourcePath = config('typefinder.resource_path', app_path('Http/Resources'));
        $this->outputPath = config('typefinder.output_path', resource_path('js/types'));
        $this->namespace = config('typefinder.namespace', 'App\\Http\\Resources');

        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Generate TypeScript files for discovered resources
     *
     * @param array $discoveredModels Array of DiscoveredModel DTOs
     */
    public function generate(array $discoveredModels): void
    {
        foreach ($discoveredModels as $model) {
            if (!$model->resource) continue;

            $resourceClass = $model->resource;
            $modelInstance = new $model->model();

            // Hydrate fake data
            $data = $resourceClass::make($modelInstance)->resolve();

            $tsInterface = $this->convertToTypeScript($data, $model->model);

            $fileName = $this->outputPath . '/' . class_basename($model->model) . '.ts';
            file_put_contents($fileName, $tsInterface);
        }

        $this->generateIndexFile();
    }

    protected function convertToTypeScript(array $data, string $modelClass): string
    {
        $lines = [];
        $lines[] = "// Generated from {$modelClass}";
        $lines[] = "";
        $lines[] = "export interface " . class_basename($modelClass) . " {";

        foreach ($data as $key => $value) {
            $type = $this->phpTypeToTsType($value);
            $lines[] = "  {$key}: {$type};";
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    protected function phpTypeToTsType(mixed $value): string
    {
        if (is_int($value)) return 'number';
        if (is_float($value)) return 'number';
        if (is_string($value)) return 'string';
        if (is_bool($value)) return 'boolean';
        if (is_array($value)) return 'any[]';
        if (is_null($value)) return 'null';
        return 'any';
    }

    protected function generateIndexFile(): void
    {
        $files = glob($this->outputPath . '/*.ts');
        $lines = [];

        foreach ($files as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $lines[] = "export * from './{$base}';";
        }

        file_put_contents($this->outputPath . '/index.ts', implode("\n", $lines));
    }
}
