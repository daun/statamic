<?php

namespace Statamic\Tags;

use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Facades\Data;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Support\Str;

class Nav extends Structure
{
    public function index()
    {
        return $this->structure($this->params->get('handle', 'collection::pages'));
    }

    public function breadcrumbs()
    {
        $currentUrl = URL::makeAbsolute(URL::getCurrent());
        $url = Str::removeLeft($currentUrl, Site::current()->absoluteUrl());
        $url = Str::ensureLeft($url, '/');
        $segments = explode('/', $url);
        $segments[0] = '/';

        if (! $this->params->bool('include_home', true)) {
            array_shift($segments);
        }

        // Assemble crumbs from segments
        $crumbs = collect()
            ->range(1, count($segments))
            ->map(fn ($i) => implode('/', array_slice($segments, 0, $i)))
            ->map(fn ($uri) => Str::ensureLeft(URL::tidy($uri), '/'))
            ->mapWithKeys(fn ($uri) => [$uri => Data::findByUri($uri, Site::current()->handle())])
            ->filter();

        // Add mount entries to crumbs
        if ($this->params->bool('include_mounts', false)) {
            $crumbs
                ->filter(fn ($crumb) => $crumb instanceof Entry)
                ->map(fn ($crumb) => $crumb->collection()->mount()?->in(Site::current()->handle()))
                ->filter()
                ->each(fn ($mount) => $crumbs->put($mount->uri(), $mount));
        }

        // Sort by path depth and filter out non-viewable items
        $crumbs = $crumbs
            ->sortKeysUsing(fn ($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'))
            ->values()
            ->reject(fn ($crumb) => $crumb instanceof Entry && ! view()->exists($crumb->template()))
            ->reject(fn ($crumb) => $crumb instanceof Taxonomy && ! view()->exists($crumb->template()))
            ->map(function ($crumb) {
                $crumb->setSupplement('is_current', URL::getCurrent() === $crumb->urlWithoutRedirect());

                return $crumb;
            });

        if (! $this->params->bool('reverse', false)) {
            $crumbs = $crumbs->reverse();
        }

        if ($this->params->bool('trim', true)) {
            $this->content = trim($this->content);
        }

        if (! $this->parser) {
            return $crumbs;
        }

        $output = $this->parseLoop($crumbs->toAugmentedArray());

        if ($backspaces = $this->params->int('backspace', 0)) {
            $output = substr($output, 0, -$backspaces);
        }

        return $output;
    }
}
