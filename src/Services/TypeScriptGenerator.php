<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\File;
use Lkrff\TypeFinder\DTO\DiscoveredModel;
use Lkrff\TypeFinder\Eloquent\TypeFinderModel;

final class TypeScriptGenerator
{
    private string $outputPath;

    /** @var string[] */
    private array $usedTypes = [];

    public function __construct(string $outputPath = null)
    {
        $this->outputPath = $outputPath ?? resource_path('js/types/generated');

        if (!File::exists($this->outputPath)) {
            File::makeDirectory($this->outputPath, 0755, true);
        }
    }

    /**
     * Generate a TS file from a single model resource.
     */
    public function generate(DiscoveredModel $model): void
    {
        $resourceClass = $model->resourceClass;
        if (! $resourceClass) return;

        /** @var TypeFinderModel $class */
        $class = $model->modelClass;

        // Row 1: fully loaded with relations
        $full = (new $class)
            ->with(array_column($model->relations, 'name'))
            ->findOrFail(1);
        $fullResource = (new $resourceClass($full))->resolve();

        // Row 2: nullable probe, no relations
        $nulls = (new $class)
            ->findOrFail(2);
        $nullsResource = (new $resourceClass($nulls))->resolve();

        $tsFields = [];

        foreach ($fullResource as $key => $value) {
            $inNullRow = array_key_exists($key, $nullsResource);

            /** Nullable if second row contains null */
            $isNullable = $inNullRow && $nullsResource[$key] === null;

            /** Optional if missing when relations are not loaded */
            $isOptional = ! $inNullRow;

            $tsType = $this->phpValueToTs($value, $isNullable);

            $optional = $isOptional ? '?' : '';

            $tsFields[] = "$key$optional: $tsType;";
        }

        $typeName = $this->resourceToTypeName($resourceClass);

        // Add imports for used types
        $imports = '';
        foreach ($this->usedTypes as $used) {
            if ($used !== $typeName) {
                $imports .= "import type { $used } from './$used';\n";
                // Create empty file if missing
                $filePath = $this->outputPath . '/' . $used . '.ts';
                if (!File::exists($filePath)) {
                    File::put($filePath, "export type $used = any;\n");
                }
            }
        }

        $tsContent =
            $imports .
            "\nexport type {$typeName} = {\n    " .
            implode("\n    ", $tsFields) .
            "\n};\n";

        File::put("{$this->outputPath}/{$typeName}.ts", $tsContent);

        // Reset used types for next model
        $this->usedTypes = [];
    }

    /**
     * Convert a PHP value / resource into a TypeScript type.
     */
    private function phpValueToTs(mixed $value, bool $nullable): string
    {
        if ($value instanceof ResourceCollection) {
            $collects = $value->collects;
            $type = $collects ? $this->resourceToTypeName($collects) . '[]' : 'any[]';
            if ($collects) $this->usedTypes[] = $this->resourceToTypeName($collects);
        } elseif ($value instanceof JsonResource) {
            $type = $this->resourceToTypeName(get_class($value));
            $this->usedTypes[] = $type;
        } elseif (is_object($value) && method_exists($value, 'value')) {
            $type = 'string';
        } else {
            $type = match (true) {
                is_int($value),
                is_float($value) => 'number',
                is_bool($value) => 'boolean',
                is_string($value) => 'string',
                is_array($value) => 'any[]',
                default => 'any',
            };
        }

        if ($nullable) $type .= ' | null';

        return $type;
    }

    /**
     * Convert a Resource class name to a TS type name.
     */
    private function resourceToTypeName(string $resourceClass): string
    {
        return preg_replace('/Resource$/', '', class_basename($resourceClass));
    }

    /**
     * Optional: clear folder before generating.
     */
    public function reset(): void
    {
        foreach (File::files($this->outputPath) as $file) {
            File::delete($file);
        }
    }

    /**
     * Generate index.ts that exports all generated types.
     */
    public function generateIndexFile(): void
    {
        $files = File::files($this->outputPath);
        $exports = [];

        foreach ($files as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $exports[] = "export * from './$name';";
        }

        File::put($this->outputPath . '/index.ts', implode("\n", $exports));
    }
}
