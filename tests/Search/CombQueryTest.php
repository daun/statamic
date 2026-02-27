<?php

namespace Tests\Search;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Search\Comb\Index;
use Statamic\Search\Comb\Query;
use Statamic\Search\SearchResponse;
use Tests\TestCase;

class CombQueryTest extends TestCase
{
    #[Test]
    public function it_returns_results()
    {
        $index = Mockery::mock(Index::class);
        $index->shouldReceive('name');
        $index->shouldReceive('lookup')->with('foo')->twice()->andReturn(new SearchResponse(collect([
            ['reference' => 'a', 'search_score' => 4],
            ['reference' => 'b', 'search_score' => 3],
            ['reference' => 'c', 'search_score' => 2],
        ]), 3));

        $query = new Query($index);
        $response = $query->getSearchResponse('foo');
        $results = $query->getSearchResults('foo');

        $this->assertInstanceOf(SearchResponse::class, $response);
        $this->assertEquals([
            ['reference' => 'a', 'search_score' => 4],
            ['reference' => 'b', 'search_score' => 3],
            ['reference' => 'c', 'search_score' => 2],
        ], $results->all());
    }
}
