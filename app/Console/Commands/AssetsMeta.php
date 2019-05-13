<?php

namespace Statamic\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\API\Asset;
use Statamic\API\AssetContainer;

class AssetsMeta extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:assets:meta { container? : Handle of a container }';

    protected $description = 'Generate asset metadata files';

    public function handle()
    {
        $assets = $this->getAssets();

        $bar = $this->output->createProgressBar($assets->count());

        $assets->each(function ($asset) use ($bar) {
            $asset->save();
            $bar->advance();
        });

        $bar->finish();

        $this->line('');
        $this->info('Asset metadata generated');
    }

    protected function getAssets()
    {
        if (! $container = $this->argument('container')) {
            return Asset::all();
        }

        if (! $container = AssetContainer::find($container)) {
            throw new \InvalidArgumentException('Invalid container');
        }

        return $container->assets();
    }
}
