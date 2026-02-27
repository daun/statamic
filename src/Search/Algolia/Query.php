<?php

namespace Statamic\Search\Algolia;

use Statamic\Facades\Blink;
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
        return Blink::once(
            "search-algolia-{$this->index->name()}-".md5($query),
            fn () => $this->index->searchUsingApi($query)
        );
    }
}
