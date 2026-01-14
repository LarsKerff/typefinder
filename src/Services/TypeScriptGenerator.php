<?php

namespace Lkrff\TypeFinder\Services;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Lkrff\TypeFinder\DTO\ColumnDefinition;
use Lkrff\TypeFinder\DTO\DiscoveredModel;

final class TypeScriptGenerator
{
    protected string $outputPath;
    protected array $generated = [];

    public function __construct()
    {
        $this->outputPath = config('typefinder.output_path', resource_path('js/types/generated'));
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    public function reset(): void
    {
        foreach (glob($this->outputPath . '/*.ts') as $file) {
            @unlink($file);
        }
        $this->generated = [];
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    public function generate(DiscoveredModel $resource): void
    {
        $interfaceName = preg_replace('/Resource$/', '', class_basename($resource->resourceClass));
        if (in_array($interfaceName, $this->generated, true)) {
            return;
        }
        $this->generated[] = $interfaceName;

        $imports = [];
        $tsInterface = $this->convertToTypeScript($resource->resourceTree, $imports, $resource->columns);

        $lines = [];

        if (!empty($imports)) {
            foreach ($imports as $import) {
                $lines[] = "import { {$import} } from './{$import}';";
            }
            $lines[] = "";
        }

        $lines[] = "// Generated from {$resource->resourceClass}";
        $lines[] = "";
        $lines[] = str_replace('REPLACED_BY_FILENAME', $interfaceName, $tsInterface);

        file_put_contents("{$this->outputPath}/{$interfaceName}.ts", implode("\n", $lines));
    }

    protected function convertToTypeScript(array $data, array &$imports, array $columns = []): string
    {
        $lines = [];
        $lines[] = "export interface REPLACED_BY_FILENAME {";

        foreach ($data as $key => $value) {
            if (is_int($key) || $value instanceof MissingValue) continue;

            $columnName = $this->fingerprintService->resolve($value)?->column;
            $nullable = $columnName ? $this->isColumnNullable($columnName, $columns) : false;

            $tsType = $this->phpValueToTsType($value, false, $nullable, $imports);
            $lines[] = "  {$key}" . ($nullable ? '?' : '') . ": {$tsType};";
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    protected function phpValueToTsType(mixed $value, bool $optional, bool $nullable, array &$imports): string
    {
        if ($value instanceof MissingValue) $value = null;

        if ($value instanceof AnonymousResourceCollection) {
            $resourceClass = $value->collects;  // TradeResource, AnswerResource, etc
            if ($resourceClass) {
                $interfaceName = preg_replace('/Resource$/', '', class_basename($resourceClass));

                if (!in_array($interfaceName, $imports, true)) {
                    $imports[] = $interfaceName;
                }

                return $interfaceName . '[]';
            }

            return 'any[]';
        }

        if ($value instanceof JsonResource) {
            $resourceClass = get_class($value);                   // StatsResource
            $interfaceName = preg_replace('/Resource$/', '', class_basename($resourceClass)); // Stats

            if (!in_array($interfaceName, $imports, true)) {
                $imports[] = $interfaceName;
            }

            return $interfaceName;
        }


        if ($value instanceof Collection) {
            $first = $value->first();
            if ($first) {
                return $this->phpValueToTsType($first, false, false, $imports) . '[]';
            }
            return 'any[] | null';
        }

        $type = match (true) {
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_array($value) => 'any[]',
            default => 'any',
        };

        return $nullable ? "{$type} | null" : $type;
    }

    protected function isColumnNullable(string $columnName, array $columns): bool
    {
        foreach ($columns as $col) {
            if ($col instanceof ColumnDefinition && $col->name === $columnName) return $col->nullable;
        }
        return false;
    }

    public function generateIndexFile(): void
    {
        $files = glob($this->outputPath . '/*.ts');
        $lines = array_map(fn($f) => "export * from './" . basename($f, '.ts') . "';", $files);
        file_put_contents("{$this->outputPath}/index.ts", implode("\n", $lines));
    }
}
