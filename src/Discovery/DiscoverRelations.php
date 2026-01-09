<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Database\Eloquent\Model;
use Lkrff\TypeFinder\Contracts\DiscoversRelations;
use Lkrff\TypeFinder\DTO\RelationMetadata;
use ReflectionClass;

final class DiscoverRelations implements DiscoversRelations
{
    public function forModel(string $modelClass): array
    {
        if (!class_exists($modelClass)) return [];

        $model = new $modelClass;
        $reflection = new ReflectionClass($model);
        $relations = [];

        $file = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (!file_exists($file)) return [];

        $lines = file($file);
        $classCode = implode("", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        preg_match_all(
            '/function\s+([a-zA-Z0-9_]+)\s*\([^\)]*\)\s*\{[^}]*\$this->(hasOne|hasMany|belongsTo|belongsToMany|hasManyThrough|morphTo|morphMany|morphToMany)\s*\(\s*([^\)]+)\)/i',
            $classCode,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            [, $methodName, $relationType, $relatedClassRaw] = $match;

            $relatedClass = $this->resolveRelatedClass($relatedClassRaw, $model);
            if (!$relatedClass) continue;

            $relations[] = new RelationMetadata(
                name: $methodName,
                relatedModel: $relatedClass,
                type: ucfirst($relationType)
            );
        }

        return $relations;
    }

    /**
     * Resolve the fully-qualified class name of a relation, or null if not resolvable.
     */
    private function resolveRelatedClass(string $raw, Model $model): ?string
    {
        $raw = trim($raw, " \t\n\r\0\x0B'\"");

        if (class_exists($raw)) {
            $ref = new ReflectionClass($raw);
            return $ref->isAbstract() ? null : $raw;
        }

        $namespace = (new ReflectionClass($model))->getNamespaceName();
        $fullClass = $namespace . '\\' . $raw;

        if (class_exists($fullClass)) {
            $ref = new ReflectionClass($fullClass);
            return $ref->isAbstract() ? null : $fullClass;
        }

        return null; // cannot resolve
    }
}
