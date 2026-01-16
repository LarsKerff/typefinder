<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\File;
use Lkrff\TypeFinder\DTO\DiscoveredModel;

final class TypeScriptGenerator
{
    private string $outputPath;

    /** @var array<string, bool> */
    private array $generated = [];

    /** @var string[] */
    private array $usedTypes = [];

    public function __construct()
    {
        $this->outputPath = resource_path(config('typefinder.output_path', 'js/types/generated'));

        if (! File::exists($this->outputPath)) {
            File::makeDirectory($this->outputPath, 0755, true);
        }
    }

    /**
     * Generate TS for a top-level Resource
     */
    public function generate(DiscoveredModel $model): void
    {
        $resourceClass = $model->resourceClass;
        if (! $resourceClass) return;

        $class = $model->modelClass;

        // Row 1 – fully loaded
        $full = (new $class)
            ->with(array_column($model->relations, 'name'))
            ->findOrFail(1);

        $fullResource = new $resourceClass($full);

        // Row 2 – nullable probe
        $nulls = (new $class)->findOrFail(2);
        $nullResource = new $resourceClass($nulls);

        $fullData = $fullResource->resolve();
        $nullData = $nullResource->resolve();

        $fields = [];
        $imports = [];
        $enums = null;
        foreach ($model->columns as $column) {
            if ($column->enum) {
                $enums = $column->enum;
            }
        }

        foreach ($fullData as $key => $value) {
            $nullable = array_key_exists($key, $nullData) && $nullData[$key] === null;
            $optional = ! array_key_exists($key, $nullData);

            $tsType = $this->phpValueToTs($value, $nullable, $model);

            if (preg_match('/^([A-Z][A-Za-z0-9_]+)/', $tsType, $m)) {
                $imports[] = $m[1];
            }

            $opt = $optional ? '?' : '';
            $fields[] = "    $key$opt: $tsType;";
        }

        $typeName = $this->resourceToTypeName($resourceClass);

        $importLines = '';
        foreach (array_unique($imports) as $import) {
            if ($import !== $typeName) {
                $importLines .= "import type { $import } from './$import';\n";
            }
        }

        $content =
            $importLines .
            "\nexport type $typeName = {\n" .
            implode("\n", $fields) .
            "\n};\n";

        File::put($this->outputPath . "/$typeName.ts", $content);
    }

    /**
     * Recursively generate TS from any JsonResource
     */
    private function generateFromResource(JsonResource $resource): void
    {
        $class = get_class($resource);
        $type = $this->resourceToTypeName($class);

        if (isset($this->generated[$type])) {
            return;
        }

        $this->generated[$type] = true;
        $this->usedTypes[] = $type;

        $data = $resource->resolve();

        $fields = [];
        $imports = [];

        foreach ($data as $key => $value) {
            $nullable = $value === null;
            $tsType = $this->phpValueToTs($value, $nullable);

            if (preg_match('/^([A-Z][A-Za-z0-9_]+)/', $tsType, $m)) {
                $imports[] = $m[1];
            }

            $fields[] = "    $key: $tsType;";
        }

        $importLines = '';
        foreach (array_unique($imports) as $import) {
            if ($import !== $type) {
                $importLines .= "import type { $import } from './$import';\n";
            }
        }

        $content =
            $importLines .
            "\nexport type $type = {\n" .
            implode("\n", $fields) .
            "\n};\n";

        File::put($this->outputPath . "/$type.ts", $content);
    }

    /**
     * Convert any PHP value into TS
     */
    private function phpValueToTs(mixed $value, bool $nullable, DiscoveredModel $model): string
    {
        // Collection of resources
        if ($value instanceof ResourceCollection) {
            $collects = $value->collects;

            if ($collects && $value->resource->first()) {
                $type = $this->resourceToTypeName($collects);
                $this->generateFromResource(
                    new $collects($value->resource->first())
                );
                return $this->nullable($type."[]", $nullable);
            }

            return $this->nullable('any[]', $nullable);
        }

        // Single resource
        if ($value instanceof JsonResource) {
            $type = $this->resourceToTypeName(get_class($value));
            $this->generateFromResource($value);
            return $this->nullable($type, $nullable);
        }

        // Enum
        if (is_object($value) && method_exists($value, 'value')) {
            return $this->nullable('string', $nullable);
        }

        // Array: list or object?
        if (is_array($value)) {
            // List → Something[]
            if (array_is_list($value)) {
                if (count($value) > 0) {
                    $inner = $this->phpValueToTs($value[0], false);
                    return $this->nullable($inner . '[]', $nullable);
                }

                return $this->nullable('any[]', $nullable);
            }

            // Associative → object
            $fields = [];

            foreach ($value as $k => $v) {
                $fields[] = "$k: " . $this->phpValueToTs($v, false) . ";";
            }

            return $this->nullable('{ ' . implode(' ', $fields) . ' }', $nullable);
        }

        $type = match (true) {
            is_int($value),
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            is_string($value) => 'string',
            is_array($value) => 'any',
            default => 'any',
        };

        return $this->nullable($type, $nullable);
    }

    private function nullable(string $type, bool $nullable): string
    {
        return $nullable ? "$type | null" : $type;
    }

    private function resourceToTypeName(string $class): string
    {
        return preg_replace('/Resource$/', '', class_basename($class));
    }

    /**
     * Clears all generated files
     */
    public function reset(): void
    {
        foreach (File::files($this->outputPath) as $file) {
            File::delete($file);
        }

        $this->generated = [];
        $this->usedTypes = [];
    }

    /**
     * Generates index.ts
     */
    public function generateIndexFile(): void
    {
        $exports = [];

        foreach (File::files($this->outputPath) as $file) {
            if ($file->getFilename() === 'index.ts') continue;

            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $exports[] = "export * from './$name';";
        }

        File::put($this->outputPath . '/index.ts', implode("\n", $exports));
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
