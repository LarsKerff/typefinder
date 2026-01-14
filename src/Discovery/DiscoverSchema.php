<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

final class DiscoverSchema implements DiscoversSchema
{
    public function __construct(
        private readonly string $connection = 'typefinder'
    ) {}

    /**
     * Return fully enriched column definitions for a model instance.
     */
    public function forModel(Model $model): array
    {
        $table = $model->getTable();
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable($table)) {
            return [];
        }

        $db = $schema->getConnection(); // PDO connection
        $columnsInfo = $db->select("PRAGMA table_info({$table})");

        // Get CREATE TABLE SQL
        $rows = DB::connection($this->connection)->select("
            SELECT sql
            FROM sqlite_master
            WHERE type = 'table' AND name = ?
        ", [$table]);

        $createSql = !empty($rows) && $rows[0]->sql ? $rows[0]->sql : '';

        $columns = [];

        foreach ($columnsInfo as $col) {
            $enrichment = $this->parseTableSql($col->name, $createSql);

            $columns[$col->name] = new ColumnDefinition(
                name: $col->name,
                type: $col->type,
                nullable: (bool) ! $col->notnull,
                enum: $enrichment['enum'] ?? null,
                boolean: $enrichment['boolean'] ?? false,
                range: $enrichment['range'] ?? null,
                maxLength: $enrichment['maxLength'] ?? null
            );
        }

        return array_values($columns);
    }

    /**
     * Parse CREATE TABLE SQL for a single column.
     *
     * Returns an array with keys: enum, boolean, range, maxLength
     */
    private function parseTableSql(string $columnName, string $sql): array
    {
        $result = [
            'enum' => null,
            'boolean' => false,
            'range' => null,
            'maxLength' => null,
        ];

        if (! $sql) {
            return $result;
        }

        // ENUM
        if (preg_match_all(
            '/"' . preg_quote($columnName, '/') . '"\s+\w+[^,]*?CHECK\s*\(\s*"\w+"\s+IN\s*\((?<vals>[^)]+)\)\)/i',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            $result['enum'] = array_map(fn($v) => trim($v, "'\" "), explode(',', $matches[0]['vals']));
        }

        // BOOLEAN
        if (preg_match(
            '/"' . preg_quote($columnName, '/') . '"[^,]*?CHECK\s*\(\s*\w+\s+IN\s*\(\s*0\s*,\s*1\s*\)\s*\)/i',
            $sql
        )) {
            $result['boolean'] = true;
        }

        // NUMERIC RANGE
        if (preg_match(
            '/CHECK\s*\(\s*"' . preg_quote($columnName, '/') . '"?\s*>=\s*(?<min>\d+)\s+AND\s+"?\w+"?\s*<=\s*(?<max>\d+)\s*\)/i',
            $sql,
            $rangeMatch
        )) {
            $result['range'] = [(float)$rangeMatch['min'], (float)$rangeMatch['max']];
        }

        // VARCHAR/CHAR length
        if (preg_match(
            '/"' . preg_quote($columnName, '/') . '"\s+(?:varchar|char)\s*\((?<len>\d+)\)/i',
            $sql,
            $lenMatch
        )) {
            $result['maxLength'] = (int)$lenMatch['len'];
        }

        return $result;
    }
}
