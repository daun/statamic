<?php

namespace Statamic\Events;

/**
 * Dispatched right before the full measure static cache writes a page to disk.
 *
 * The file still contains the previous version of the page, or doesn't exist yet.
 * Listeners run synchronously and must not implement ShouldQueue.
 */
class StaticCacheFileWriting extends Event
{
    public function __construct(public string $path, public ?string $url, public ?string $site)
    {
    }
}
