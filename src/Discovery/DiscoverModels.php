<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Database\Eloquent\Model;
use Lkrff\TypeFinder\Contracts\DiscoversModels;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class DiscoverModels implements DiscoversModels
{
    public function all(): array
    {
        $basePath = app_path('Models');
        $baseNamespace = 'App\\Models\\';

        if (!is_dir($basePath)) {
            return [];
        }

        $models = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath)) as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$basePath . DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $class = $baseNamespace . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (!class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);

            if ($ref->isAbstract() || !$ref->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }
}
