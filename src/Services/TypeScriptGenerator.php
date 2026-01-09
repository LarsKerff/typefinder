<?php

namespace Lkrff\TypeFinder\Services;

use Lkrff\TypeFinder\DTO\Model as DiscoveredModel;
use Lkrff\TypeFinder\DTO\ColumnDefinition;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function generateFromResolved(DiscoveredModel $model, array $data, array $columns = []): void
    {
        $interfaceName = class_basename($model->model);

        if (in_array($interfaceName, $this->generated)) {
            return;
        }
        $this->generated[] = $interfaceName;

        $imports = [];
        $tsInterface = $this->convertToTypeScript($data, $imports, $columns);

        $lines = [];

        if (!empty($imports)) {
            foreach ($imports as $import) {
                $lines[] = "import { {$import} } from './{$import}';";
            }
            $lines[] = "";
        }

        $lines[] = "// Generated from {$model->model}";
        $lines[] = "";
        $lines[] = str_replace('REPLACED_BY_FILENAME', $interfaceName, $tsInterface);

        file_put_contents($this->outputPath . "/{$interfaceName}.ts", implode("\n", $lines));
    }

    protected function convertToTypeScript(array $data, array &$imports, array $columns = []): string
    {
        $lines = [];
        $interfaceName = 'REPLACED_BY_FILENAME';
        $lines[] = "export interface {$interfaceName} {";

        foreach ($data as $key => $value) {
            $optional = $value instanceof MissingValue;
            $nullable = $this->isColumnNullable($key, $columns);

            $type = $this->phpValueToTsType($value, $optional, $nullable, $imports);
            $lines[] = "  {$key}" . ($optional ? '?' : '') . ": {$type};";
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    protected function phpValueToTsType(mixed $value, bool $optional, bool $nullable, array &$imports): string
    {
        if ($value instanceof MissingValue) {
            $value = null;
        }

        $type = 'any';

        // Single JsonResource
        if ($value instanceof JsonResource) {
            $type = $this->handleResource($value, false, $imports, $optional);
        }
        // AnonymousResourceCollection (or objects that expose a public ->collects)
        elseif ($value instanceof AnonymousResourceCollection || (is_object($value) && property_exists($value, 'collects'))) {
            $type = $this->handleAnonymousCollection($value, $imports);
        }
        // Generic Collection (from whenLoaded)
        elseif ($value instanceof Collection) {
            if ($value->isEmpty()) {
                $type = 'any[]';
            } else {
                $first = $value->first();

                if ($first instanceof JsonResource) {
                    $type = $this->handleResource($first, true, $imports, $optional);
                } else {
                    $itemType = $this->phpValueToTsType($first, false, false, $imports);
                    $type = "{$itemType}[]";
                }
            }
        }
        elseif (is_int($value) || is_float($value)) {
            $type = 'number';
        }
        elseif (is_string($value)) {
            $type = 'string';
        }
        elseif (is_bool($value)) {
            $type = 'boolean';
        }
        elseif (is_array($value)) {
            $type = 'any[]';
        }

        if ($nullable && !str_contains($type, 'null')) {
            $type .= ' | null';
        }

        return $type;
    }

    protected function handleAnonymousCollection($collection, array &$imports): string
    {
        $collects = $collection->collects ?? null;
        if ($collects) {
            $interfaceName = class_basename($collects);

            if (!in_array($interfaceName, $imports)) {
                $imports[] = $interfaceName;
            }

            if (!isset($this->generatedResources[$collects])) {
                $this->generatedResources[$collects] = true;

                $firstItem = null;
                if (method_exists($collection, 'first')) {
                    $firstItem = $collection->first();
                }

                if ($firstItem instanceof JsonResource) {
                    $this->generateResourceFile($interfaceName, $firstItem->toArray(request()));
                }
            }

            $type = $interfaceName . '[]';

            if ($collection->isEmpty()) {
                $type .= ' | null';
            }

            return $type;
        }

        return 'any[]';
    }

    protected function isColumnNullable(string $columnName, array $columns): bool
    {
        foreach ($columns as $col) {
            if ($col instanceof ColumnDefinition && $col->name === $columnName) {
                return $col->nullable;
            }
        }
        return false;
    }

    protected function handleResource(JsonResource $resource, bool $isCollection, array &$imports, bool $optional): string
    {
        $resourceClass = get_class($resource);
        $interfaceName = class_basename($resourceClass);

        if (!in_array($interfaceName, $imports)) {
            $imports[] = $interfaceName;
        }

        if (!isset($this->generatedResources[$resourceClass])) {
            $this->generatedResources[$resourceClass] = true;
            $this->generateResourceFile($interfaceName, $resource->toArray(request()));
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
