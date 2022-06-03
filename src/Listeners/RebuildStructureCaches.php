<?php

namespace Statamic\Listeners;

use Statamic\Events\CollectionDeleted;
use Statamic\Events\CollectionSaved;
use Statamic\Events\CollectionTreeDeleted;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\NavDeleted;
use Statamic\Events\NavSaved;
use Statamic\Events\NavTreeDeleted;
use Statamic\Facades\Blink;
use Statamic\Events\NavTreeSaved;
use Statamic\Structures\Page;
use Statamic\Structures\PageCache;
use Statamic\Structures\Tree;

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
            EntrySaved::class,
        ], [self::class, 'rebuildEntryPageCaches']);

        $events->listen([
            EntryDeleted::class,
        ], [self::class, 'forgetEntryPageCaches']);

        $events->listen([
            CollectionDeleted::class,
            CollectionSaved::class,
        ], [self::class, 'handleCollectionEvents']);

        $events->listen([
            CollectionTreeSaved::class,
            NavTreeSaved::class,
        ], [self::class, 'rebuildTreePageCaches']);

        $events->listen([
            CollectionTreeDeleted::class,
            NavTreeDeleted::class,
        ], [self::class, 'forgetTreePageCaches']);
    }

    public function handleCollectionEvents($event)
    {
        // TODO: All page caches for pages in the collection can be erased
    }

    public function rebuildEntryPageCaches($event)
    {
        $entry = $event->entry;

        if (! $entry->collection()->hasStructure()) {
            return;
        }

        $page = $entry->collection()->structure()->in($entry->site()->handle())
            ->flattenedPages()
            ->where('id', $entry->id())
            ->first();

        if ($page) {
            $this->rebuildPageCaches($page);
        }
    }

    public function forgetEntryPageCaches($event)
    {
        $entry = $event->entry;

        if (! $entry->collection()->hasStructure()) {
            return;
        }

        $page = $entry->collection()->structure()->in($entry->site()->handle())
            ->flattenedPages()
            ->where('id', $entry->id())
            ->first();

        if ($page) {
            $this->forgetPageCaches($page);
        }
    }

    public function rebuildTreePageCaches($event)
    {
        if ($pages = $event->tree->pages()) {
            $pages->all()->each(function ($page) {
                $this->rebuildPageCaches($page);
            });
        }
    }

    public function forgetTreePageCaches($event)
    {
        if ($pages = $event->tree->pages()) {
            $pages->all()->each(function ($page) {
                $this->forgetPageCaches($page);
            });
        }
    }

    protected function rebuildPageCaches(Page $page)
    {
        (new PageCache($page))->rebuildAll([
            'toAugmentedArray' => [['*']],
            'toAugmentedArray' => [['@shallow']],
            'urlWithoutRedirect' => [],
            'absoluteUrl' => [],
        ]);
    }

    protected function forgetPageCaches(Page $page)
    {
        (new PageCache($page))->forgetAll();
    }
}
