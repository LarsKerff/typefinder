<?php

namespace Lkrff\TypeFinder\Contracts;

use Illuminate\Database\Eloquent\Model;
use Lkrff\TypeFinder\DTO\ColumnDefinition;

interface DiscoversSchema
{
    /**
     * Return column definitions for a given model instance.
     *
     * @param Model $model
     * @return ColumnDefinition[]
     */
    public function forModel(Model $model): array;
}
