<?php

namespace Tests\Benchmark;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\AssetSaved;
use Statamic\Facades;
use Statamic\Listeners\UpdateAssetReferences;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class ReferenceUpdateBenchmarkTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $container;
    private $assetHoff;
    private $assetNorris;

    public function setUp(): void
    {
        parent::setUp();

        // A fatal error in a previous run skips the trait's teardown; start from a clean slate.
        app('files')->deleteDirectory($this->fakeStacheDirectory, true);

        config(['cache.default' => 'file']);
        Cache::clear();

        config(['filesystems.disks.test' => [
            'driver' => 'local',
            'root' => __DIR__.'/tmp',
        ]]);

        $this->container = tap(Facades\AssetContainer::make()->handle('test_container')->disk('test'))->save();
        $this->assetHoff = tap(Facades\Asset::make()->container('test_container')->path('hoff.jpg'))->save();
        $this->assetNorris = tap(Facades\Asset::make()->container('test_container')->path('norris.jpg'))->save();

        Storage::fake('test');
    }

    public function tearDown(): void
    {
        app('files')->deleteDirectory(__DIR__.'/tmp');

        app('files')->delete(Facades\Blueprint::find('bench_article')?->path());

        parent::tearDown();
    }

    #[Test]
    public function benchmark_asset_reference_update()
    {
        $total = (int) (getenv('BENCH_ENTRIES') ?: 2000);
        $referencing = 10;

        $collection = tap(Facades\Collection::make('articles'))->save();

        $blueprint = tap(Facades\Blueprint::make('bench_article')->setNamespace('collections.articles')->setContents([
            'fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'hero', 'field' => ['type' => 'assets', 'container' => 'test_container', 'max_files' => 1]],
                ['handle' => 'body', 'field' => ['type' => 'markdown', 'container' => 'test_container']],
                ['handle' => 'content', 'field' => [
                    'type' => 'bard',
                    'container' => 'test_container',
                    'sets' => [
                        'media' => ['fields' => [
                            ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'test_container', 'max_files' => 1]],
                            ['handle' => 'caption', 'field' => ['type' => 'text']],
                        ]],
                    ],
                ]],
                ['handle' => 'gallery', 'field' => ['type' => 'grid', 'fields' => [
                    ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'test_container', 'max_files' => 1]],
                ]]],
                ['handle' => 'seo_description', 'field' => ['type' => 'text']],
            ],
        ]))->save();

        Facades\Blueprint::shouldReceive('in')->with('collections/articles')->andReturn(collect([$blueprint]));

        for ($i = 0; $i < $total; $i++) {
            $asset = $i < $referencing ? 'hoff.jpg' : 'norris.jpg';

            Facades\Entry::make()->collection($collection)->data([
                'title' => "Entry {$i}",
                'hero' => $asset,
                'body' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. ![pic](statamic://asset::test_container::{$asset}) Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.']]],
                    ['type' => 'image', 'attrs' => ['src' => "asset::test_container::{$asset}", 'alt' => 'A picture']],
                    ['type' => 'set', 'attrs' => ['values' => ['type' => 'media', 'image' => $asset, 'caption' => 'A caption']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.']]],
                ],
                'gallery' => [
                    ['image' => $asset],
                    ['image' => 'norris.jpg'],
                ],
                'seo_description' => 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
            ])->save();
        }

        // Warm the Stache item + path caches so we measure the listener, not cold cache building.
        $hydrated = Facades\Entry::query()->lazy()->count();
        $this->assertEquals($total, $hydrated);

        // Simulate a rename: path changed, original path still tracked on the asset.
        $this->assetHoff->path('moved/hoff.jpg');

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $timeStart = microtime(true);

        app(UpdateAssetReferences::class)->handleSaved(new AssetSaved($this->assetHoff));

        $elapsed = (microtime(true) - $timeStart) * 1000;
        $memAfter = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);

        // Sanity: the referencing entries were actually updated.
        $updated = Facades\Entry::query()->where('hero', 'moved/hoff.jpg')->count();
        $this->assertEquals($referencing, $updated);

        fwrite(STDERR, sprintf(
            "\n[bench] entries=%d referencing=%d listener_ms=%.0f mem_retained_mb=%.1f mem_peak_mb=%.1f\n",
            $total,
            $referencing,
            $elapsed,
            ($memAfter - $memBefore) / 1048576,
            $memPeak / 1048576
        ));
    }
}
