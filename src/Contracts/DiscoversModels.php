<?php

namespace Lkrff\TypeFinder\Contracts;

interface DiscoversModels
{
    /**
     * Return a list of all model class names to consider.
     *
     * @return class-string[]
     */
    public function all(): array;
}
