<?php

namespace Statamic\Events;

/**
 * Dispatched right before the full measure static cache deletes a page from disk.
 *
 * The file still exists. Listeners run synchronously and must not implement ShouldQueue.
 */
class StaticCacheFileDeleting extends Event
{
    public function __construct(public string $path, public ?string $url, public ?string $site)
    {
    }
}
