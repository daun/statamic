<?php

namespace Statamic\Search\Comb;

use Statamic\Search\QueryBuilder;
use Statamic\Search\SearchResponse;

class Query extends QueryBuilder
{
    public function getSearchResults($query)
    {
        return $this->getSearchResponse($query)->getResults();
    }

    public function getSearchResponse($query): SearchResponse
    {
        return $this->index->lookup($query);
    }
}
