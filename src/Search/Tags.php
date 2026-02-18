<?php

namespace Statamic\Search;

use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Tags\Concerns;
use Statamic\Tags\Tags as BaseTags;

class Tags extends BaseTags
{
    use Concerns\GetsQueryResults {
        results as getQueryResults;
    }
    use Concerns\OutputsItems,
        Concerns\QueriesConditions,
        Concerns\QueriesOrderBys,
        Concerns\QueriesScopes;

    protected static $handle = 'search';

    /**
     * The {{ search }} tag. Includes results and additional metadata from the search driver.
     */
    public function index()
    {
        if (! $this->getSearchQuery()) {
            return $this->parseNoResults();
        }

        $builder = $this->createSearchQueryBuilder();
        $results = $this->getQueryResults($builder);

        return $this->output($results);
    }

    /**
     * The {{ search:results }} tag. Result data only.
     */
    public function results()
    {
        if (! $this->getSearchQuery()) {
            return $this->parseNoResults();
        }

        $builder = $this->createSearchQueryBuilder();
        $results = $this->getQueryResults($builder);

        return $this->output($results);
    }

    protected function getSearchQuery(): mixed
    {
        return $this->params->get('for') ?? request($this->params->get('query', 'q'));
    }

    protected function createSearchQueryBuilder()
    {
        $builder = Search::index($this->params->get('index'))
            ->ensureExists()
            ->search($this->getSearchQuery())
            ->withData($this->params->get('supplement_data', true));

        $this->querySite($builder);
        $this->queryStatus($builder);
        $this->queryConditions($builder);
        $this->queryScopes($builder);
        $this->queryOrderBys($builder);

        return $builder;
    }

    protected function queryStatus($query)
    {
        if ($this->isQueryingCondition('status') || $this->isQueryingCondition('published')) {
            return;
        }

        return $query->where('status', 'published');
    }

    protected function querySite($query)
    {
        $sites = $this->params->explode(['site', 'locale'], [Site::current()->handle()]);

        if (in_array('*', $sites) || ! Site::hasMultiple()) {
            return;
        }

        return $query->whereIn('site', $sites);
    }
}
