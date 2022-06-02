<?php

namespace Statamic\Listeners;

use Statamic\Events\CollectionSaved;
use Statamic\Events\CollectionDeleted;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\CollectionTreeDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\NavSaved;
use Statamic\Events\NavDeleted;
use Statamic\Events\NavTreeSaved;
use Statamic\Events\NavTreeDeleted;
use Statamic\Structures\PageCache;

class RebuildStructureCaches
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen([
            EntryDeleted::class,
            EntrySaved::class,
        ], [self::class, 'handleEntryEvents']);

        $events->listen([
            CollectionDeleted::class,
            CollectionSaved::class,
            CollectionTreeDeleted::class,
            CollectionTreeSaved::class,
        ], [self::class, 'handleCollectionEvents']);

        $events->listen([
            NavDeleted::class,
            NavSaved::class,
            NavTreeDeleted::class,
            NavTreeSaved::class,
        ], [self::class, 'handleNavEvents']);
    }

    public function handleCollectionEvents($event)
    {
        // TODO: All page caches for pages in the collection can be erased
    }

    public function handleEntryEvents($event)
    {
        $entry = $event->entry;

        $page = $entry->collection()->structure()->in($entry->site()->handle())
            ->flattenedPages()
            ->where('id', $entry->id())
            ->first();

        $this->rebuildPageCaches($page);
    }

    public function handleNavEvents($event)
    {
        $tree = $event->tree->tree();

        $event->tree->flattenedPages()->each(function ($page) {
            $this->rebuildPageCaches($page);
        });
    }

    protected function rebuildPageCaches($page)
    {
        (new PageCache($page))->rebuildAll([
            'toAugmentedArray',
            'urlWithoutRedirect',
            'absoluteUrl',
        ]);
    }
}
