<?php

namespace Tests\Search;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Search\Algolia\Index;
use Statamic\Search\Algolia\Query;
use Statamic\Search\SearchResponse;
use Tests\TestCase;

class AlgoliaQueryTest extends TestCase
{
    #[Test]
    public function it_returns_response_and_results()
    {
        $index = Mockery::mock(Index::class);
        $index->shouldReceive('name');
        $index->shouldReceive('searchUsingApi')->with('foo')->once()->andReturn(new SearchResponse(collect([
            ['reference' => 'a', 'search_score' => 3],
            ['reference' => 'b', 'search_score' => 2],
            ['reference' => 'c', 'search_score' => 1],
        ]), 3));

        $query = new Query($index);
        $response = $query->getSearchResponse('foo');
        $results = $query->getSearchResults('foo');

        $this->assertInstanceOf(SearchResponse::class, $response);
        $this->assertEquals([
            ['reference' => 'a', 'search_score' => 3],
            ['reference' => 'b', 'search_score' => 2],
            ['reference' => 'c', 'search_score' => 1],
        ], $results->all());
    }
}
