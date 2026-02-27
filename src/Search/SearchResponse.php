<?php

namespace Statamic\Search;

use Illuminate\Support\Collection;

class SearchResponse
{
    public function __construct(
        protected Collection $results,
        protected int $total,
        protected array $aggregations = [],
    ) {
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getResults(): Collection
    {
        return $this->results;
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }
}
