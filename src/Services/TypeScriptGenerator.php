<?php

namespace Lkrff\TypeFinder\Services;

use Lkrff\TypeFinder\DTO\Model as DiscoveredModel;
use Illuminate\Http\Resources\MissingValue;

final class TypeScriptGenerator
{
    protected string $outputPath;
    protected array $generated = [];
    protected array $generatedResources = [];

    public function __construct()
    {
        $this->outputPath = config('typefinder.output_path', resource_path('js/types/generated'));
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Wipe all previously generated files and reset generator state
     */
    public function reset(): void
    {
        if (is_dir($this->outputPath)) {
            foreach (glob($this->outputPath . '/*.ts') as $file) {
                @unlink($file);
            }
        }

        $this->generated = [];
        $this->generatedResources = [];
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * Generate TypeScript interface from a resolved resource array
     */
    public function generateFromResolved(DiscoveredModel $model, array $data): void
    {
        $interfaceName = class_basename($model->model);

        if (in_array($interfaceName, $this->generated)) {
            return;
        }

        $this->generated[] = $interfaceName;

        $imports = [];
        $tsInterface = $this->convertToTypeScript($data, $imports);

        $lines = [];

        // Nested imports
        if (!empty($imports)) {
            foreach ($imports as $import) {
                $lines[] = "import { {$import} } from './{$import}';";
            }
            $lines[] = "";
        }

        $lines[] = "// Generated from {$model->model}";
        $lines[] = "";
        $lines[] = str_replace('REPLACED_BY_FILENAME', $interfaceName, $tsInterface);

        file_put_contents($this->outputPath . '/' . $interfaceName . '.ts', implode("\n", $lines));
    }

    /**
     * Convert resolved resource array to TypeScript interface
     */
    protected function convertToTypeScript(array $data, array &$imports): string
    {
        $lines = [];
        $interfaceName = 'REPLACED_BY_FILENAME';
        $lines[] = "export interface {$interfaceName} {";

        foreach ($data as $key => $value) {
            $optional = $value instanceof MissingValue;
            $type = $this->phpValueToTsType($value, $optional, $imports);
            $lines[] = "  {$key}" . ($optional ? '?' : '') . ": {$type};";
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    /**
     * Map PHP value to TypeScript type, track nested resources
     */
    protected function phpValueToTsType(mixed $value, bool $optional, array &$imports): string
    {
        if ($value instanceof MissingValue) {
            $value = null;
        }

        // Single JsonResource
        if ($value instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            return $this->handleResource($value, false, $imports, $optional);
        }

        // Resource collection
        if ($value instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection) {
            $first = $value->first();
            
            if ($first instanceof \Illuminate\Http\Resources\Json\JsonResource) {
                return $this->handleResource($first, true, $imports, $optional);
            }

            return 'any[]';
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            if ($value->isEmpty())
                return 'any[]';

            $first = $value->first();
            $itemType = $this->phpValueToTsType($first, false, $imports);
            return "{$itemType}[]";
        }


        if (is_int($value) || is_float($value))
            return 'number';
        if (is_string($value))
            return 'string';
        if (is_bool($value))
            return 'boolean';
        if (is_array($value))
            return 'any[]';
        if ($value === null)
            return 'any';

        return 'any';
    }

    protected function handleResource(
        \Illuminate\Http\Resources\Json\JsonResource $resource,
        bool $isCollection,
        array &$imports,
        bool $optional
    ): string {
        $resourceClass = get_class($resource);
        $interfaceName = class_basename($resourceClass);

        // Register import
        if (!in_array($interfaceName, $imports)) {
            $imports[] = $interfaceName;
        }

        // Generate resource file once
        if (!isset($this->generatedResources[$resourceClass])) {
            $this->generatedResources[$resourceClass] = true;

            $data = $resource->toArray(request());
            $this->generateResourceFile($interfaceName, $data);
        }

        $type = $interfaceName . ($isCollection ? '[]' : '');

        if ($optional) {
            $type .= ' | null';
        }

        return $type;
    }

    protected function generateResourceFile(string $interfaceName, array $data): void
    {
        $imports = [];
        $ts = $this->convertToTypeScript($data, $imports);

        $lines = [];

        if (!empty($imports)) {
            foreach ($imports as $import) {
                $lines[] = "import { {$import} } from './{$import}';";
            }
            $lines[] = "";
        }

        $lines[] = "// Generated from Resource {$interfaceName}";
        $lines[] = "";
        $lines[] = str_replace('REPLACED_BY_FILENAME', $interfaceName, $ts);

        file_put_contents($this->outputPath . "/{$interfaceName}.ts", implode("\n", $lines));
    }

    /**
     * Generate index.ts exporting all interfaces
     */
    public function generateIndexFile(): void
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
