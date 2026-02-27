<?php

namespace Tests\Search;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Search\Algolia\Index as AlgoliaIndex;
use Statamic\Search\SearchResponse;
use Tests\TestCase;

class AlgoliaIndexTest extends TestCase
{
    use IndexTests;

    public function getIndexClass()
    {
        return AlgoliaIndex::class;
    }

    public function getIndex($name, $config, $locale, $client = null)
    {
        $client ??= Mockery::mock(\Algolia\AlgoliaSearch\Api\SearchClient::class);

        return new AlgoliaIndex($client, $name, $config, $locale);
    }

    #[Test]
    public function it_transforms_response()
    {
        $client = Mockery::mock(\Algolia\AlgoliaSearch\Api\SearchClient::class);
        $index = $this->getIndex('test', [], null, $client);
        $client->shouldReceive('searchSingleIndex')->with('test', ['query' => 'foo'])->once()->andReturn([
            'nbHits' => 5,
            'nbPages' => 2,
            'processingTimeMS' => 30,
            'hits' => [
                ['objectID' => 'a'],
                ['objectID' => 'b'],
                ['objectID' => 'c'],
            ],
        ]);

        $response = $index->searchUsingApi('foo');

        $this->assertInstanceOf(SearchResponse::class, $response);
        $this->assertEquals([
            ['reference' => 'a', 'search_score' => 3],
            ['reference' => 'b', 'search_score' => 2],
            ['reference' => 'c', 'search_score' => 1],
        ], $response->getResults()->all());
        $this->assertEquals(5, $response->getTotal());
        $this->assertEquals([
            'nbHits' => 5,
            'nbPages' => 2,
            'processingTimeMS' => 30,
        ], $response->getAggregations());
    }
}
