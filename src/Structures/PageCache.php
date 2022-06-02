<?php

namespace Statamic\Structures;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PageCache
{
    protected $page;
    protected $ref;
    protected $force = false;

    public function __construct(Page $page)
    {
        $this->page = $page;
        $this->ref = $page->reference() ?? $page->id();
    }

    /**
     * Call some method on the underlying page, but cache the result.
     */
    public function get($method, ...$params)
    {
        $this->index($method, $params);

        $decorator = Str::camel('get_'.Str::snake($method));

        if (method_exists($this, $decorator)) {
            return $this->$decorator(...$params);
        }

        return $this->remember("{$this->indexKey()}:{$method}", fn () => $this->page->$method(...$params));
    }

    public function rebuildAll(array $prebuild = [])
    {
        if (empty($caches = Cache::get($this->indexKey(), [])) && ! empty($prebuild)) {
            $this->prebuild($prebuild);

            return;
        }

        foreach ($caches as $method => $params) {
            $this->force()->get($method, ...$params);
        }
    }

    public function forgetAll()
    {
        foreach (Cache::get($this->indexKey(), []) as $method => $params) {
            Cache::forget("{$this->indexKey()}:{$method}");
        }

        Cache::forget($this->indexKey());
    }

    /**
     * Keep a tally of all of the different methods we've called, with their parameters,
     * so we can opportunistically rebuild these caches later.
     */
    protected function index(string $method, ?array $params = []): void
    {
        $cacheKeys = Cache::get($indexKey = $this->indexKey(), []);

        $cacheKeys = ! isset($cacheKeys[$method])
            ? array_merge($cacheKeys, [$method => $params])
            : $cacheKeys;

        Cache::put($indexKey, $cacheKeys);
    }

    protected function indexKey()
    {
        return "pages:{$this->ref}";
    }

    /**
     * toAugmentedArray is a special case because we need to cache the exact structure as was selected,
     * so we need to key the cache based.
     */
    protected function getToAugmentedArray(?array $keys = [])
    {
        $cacheKeyPrefix = "{$this->indexKey()}:toAugmentedArray";
        $selectHash = md5(json_encode($keys ?? []));

        return $this->remember(
            "{$cacheKeyPrefix}:{$selectHash}",
            fn () => $this->page->toAugmentedArray($keys)
        );
    }

    protected function prebuild(array $methods)
    {
        foreach ($methods as $method) {
            $this->get($method);
        }
    }

    protected function remember($key, $callable)
    {
        if ($this->force) {
            Cache::forget($key);
            $this->force = false;
        }

        return Cache::rememberForever($key, $callable);
    }

    protected function force()
    {
        $this->force = true;

        return $this;
    }
}
