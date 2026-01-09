<?php

namespace Lkrff\TypeFinder\Helpers;

final class TypeMapper
{
    public static function toTsType(string $dbType, bool $nullable = false): string
    {
        $tsType = match ($dbType) {
            'int', 'integer', 'bigint', 'smallint', 'int8', 'int4' => 'number',
            'float', 'double', 'decimal', 'numeric', 'real' => 'number',
            'boolean', 'bool' => 'boolean',
            'json', 'jsonb' => 'any', // could also be object | array
            'date', 'datetime', 'timestamp', 'timestamptz', 'timestamp(0) without time zone' => 'string',
            'varchar', 'char', 'text', 'string' => 'string',
            default => 'any',
        };

        return $nullable ? "$tsType | null" : $tsType;
    }
}
