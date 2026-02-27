<?php

namespace Statamic\Search;

use Illuminate\Support\Collection;

class SearchResponse
{
    public function __construct(
        protected int $total,
        protected Collection $results,
        protected array $aggregations = [],
    ) {}

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
