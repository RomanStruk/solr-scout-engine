<?php

namespace RomanStruk\SolrScoutEngine;

class Builder extends \Laravel\Scout\Builder
{
    /**
     * Get the total number of results from the Scout engine, or fallback to query builder.
     *
     * @param  mixed  $results
     * @return int
     */
    protected function getTotalCount($results)
    {
        return $this->engine()->getTotalCount($results);
    }
}
