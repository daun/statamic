<?php

namespace Tests\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\AssetSaved;
use Statamic\Facades;
use Statamic\Listeners\UpdateAssetReferences;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class GetsItemsContainingDataTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $assetHoff;

    public function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'file']); // Doesn't work when they're arrays since the object is stored in memory.
        Cache::clear();

        config(['filesystems.disks.test' => [
            'driver' => 'local',
            'root' => __DIR__.'/tmp',
        ]]);

        tap(Facades\AssetContainer::make()->handle('test_container')->disk('test'))->save();
        $this->assetHoff = tap(Facades\Asset::make()->container('test_container')->path('hoff.jpg'))->save();

        Storage::fake('test');
    }

    public function tearDown(): void
    {
        app('files')->deleteDirectory(__DIR__.'/tmp');

        parent::tearDown();
    }

    #[Test]
    public function queries_pass_through_the_raw_content_filter()
    {
        $entry = $this->createEntryWithHoffHeroImage();

        $listener = new class extends UpdateAssetReferences
        {
            public $received = [];

            protected function applyRawContentFilter($query)
            {
                $this->received[] = get_class($query);

                return parent::applyRawContentFilter($query);
            }
        };

        $this->assetHoff->path('destination/hoff.jpg');

        $listener->handleSaved(new AssetSaved($this->assetHoff));

        $this->assertCount(3, $listener->received); // entry, term, and user queries

        $this->assertEquals('destination/hoff.jpg', $entry->fresh()->get('hero'));
    }

    #[Test]
    public function an_overridden_raw_content_filter_narrows_the_iteration()
    {
        $entry = $this->createEntryWithHoffHeroImage();

        $listener = new class extends UpdateAssetReferences
        {
            protected $needle;

            protected function replaceReferences($asset, $originalPath, $newPath)
            {
                $this->needle = $originalPath;

                parent::replaceReferences($asset, $originalPath, $newPath);
            }

            protected function applyRawContentFilter($query)
            {
                if ($this->needle && $query instanceof EntryQueryBuilder) {
                    $query->where('id', 'no-such-id');
                }

                return $query;
            }
        };

        $this->assetHoff->path('destination/hoff.jpg');

        $listener->handleSaved(new AssetSaved($this->assetHoff));

        $this->assertEquals('hoff.jpg', $entry->fresh()->get('hero'));
    }

    #[Test]
    public function the_needle_is_available_to_the_filter_when_stashed_from_replace_references()
    {
        $this->createEntryWithHoffHeroImage();

        $listener = new class extends UpdateAssetReferences
        {
            public $needles = [];

            protected $needle;

            protected function replaceReferences($asset, $originalPath, $newPath)
            {
                $this->needle = $originalPath;

                parent::replaceReferences($asset, $originalPath, $newPath);
            }

            protected function applyRawContentFilter($query)
            {
                $this->needles[] = $this->needle;

                return $query;
            }
        };

        $this->assetHoff->path('destination/hoff.jpg');

        $listener->handleSaved(new AssetSaved($this->assetHoff));

        $this->assertEquals(['hoff.jpg', 'hoff.jpg', 'hoff.jpg'], $listener->needles);
    }

    protected function createEntryWithHoffHeroImage()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $blueprint = tap(Facades\Blueprint::make('set-in-blueprints')->setContents([
            'fields' => [
                [
                    'handle' => 'hero',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                        'max_files' => 1,
                    ],
                ],
            ],
        ]))->save();

        Facades\Blueprint::shouldReceive('in')->with('collections/articles')->andReturn(collect([$blueprint]));

        return tap(Facades\Entry::make()->collection($collection)->data([
            'hero' => $this->assetHoff->path(),
        ]))->save();
    }
}
