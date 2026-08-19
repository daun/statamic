<?php

namespace Statamic\Listeners\Concerns;

use Illuminate\Support\LazyCollection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Support\Traits\Hookable;

trait GetsItemsContainingData
{
    use Hookable;

    /**
     * Get items containing data.
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function getItemsContainingData()
    {
        $collections = [
            LazyCollection::make(function () {
                yield from $this->applyRawContentFilter(Entry::query())->lazy();
            }),
            LazyCollection::make(function () {
                yield from $this->applyRawContentFilter(Term::query())->lazy();
            }),
            LazyCollection::make(function () {
                yield from GlobalSet::all()->flatMap(fn ($set) => $set->localizations()->values());
            }),
            LazyCollection::make(function () {
                yield from $this->applyRawContentFilter(User::query())->lazy();
            }),
            LazyCollection::make(function () {
                yield from ($this->runHooks('additional') ?? LazyCollection::make());
            }),
        ];

        return LazyCollection::make(function () use ($collections) {
            foreach ($collections as $collection) {
                yield from $collection;
            }
        });
    }

    /**
     * Narrow a query to items whose raw stored data could contain the value being replaced.
     *
     * Overrides may over-match (every item is re-checked before anything is updated)
     * but must never exclude an item whose stored data contains the value verbatim.
     * The default is a no-op, which is always correct.
     *
     * @param  \Statamic\Contracts\Query\Builder  $query
     * @return \Statamic\Contracts\Query\Builder
     */
    protected function applyRawContentFilter($query)
    {
        return $query;
    }
}
