<?php

namespace Lkrff\TypeFinder\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class TypeFinderModel extends Model
{
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        $discovered = app(TypeRegistry::class)->for(static::class);

        if ($discovered && $column = $discovered->column($key)) {
            $column->observe($value);
        }

        return $value;
    }

}
