<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

if (! function_exists('ddSql')) {
    /**
     * Dump the SQL with bindings.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    function ddSql($query)
    {
        if ($query instanceof EloquentBuilder) {
            $query = $query->getQuery();
        }

        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'{$binding}'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        dd($sql);
    }
}