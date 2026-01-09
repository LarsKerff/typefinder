<?php

namespace Lkrff\TypeFinder\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lkrff\TypeFinder\Contracts\DiscoversSchema;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

final class DiscoverSchema implements DiscoversSchema
{
    /**
     * Return column definitions for a given model instance.
     *
     * @param Model $model
     * @return ColumnDefinition[]
     */
    public function forModel(Model $model): array
    {
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[] = new ColumnDefinition(
                name: $column['name'],
                type: $column['type_name'], // or use 'type' for full type info
                nullable: $column['nullable'],
            );
        }

        return $columns;
    }
}
