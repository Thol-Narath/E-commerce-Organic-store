<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Central response-caching layer for read-heavy API endpoints.
 *
 * Values are stored in a "versioned bucket": every cache key for a bucket is
 * prefixed with the bucket's current version. Invalidating a bucket simply
 * bumps its version counter, which instantly makes every old key stale without
 * needing pattern deletes (the file cache driver cannot select keys by prefix).
 *
 * Buckets (and the data that lives under them):
 *   - products    product lists / featured / detail pages
 *   - categories  category lists / detail / category products
 *   - content     banners, testimonials, blogs
 *   - settings    public store settings payloads
 *   - stats       public store statistics
 *   - orders      admin order statistics KPIs
 *   - revenue     admin revenue-trend charts
 *
 * Everything stored is plain (serializable) data — models and paginators are
 * never cached, only their already-resolved Resource arrays.
 */
class CacheService
{
    public const TTL_SHORT = 300;   // 5 min — product pages stay freshest

    public const TTL_MEDIUM = 600;  // 10 min — catalog lists + content

    public const TTL_LONG = 3600;   // 1 hour — settings, stats, config

    /**
     * Read (and compute) a value from a versioned bucket.
     *
     * @param  array|string  $keyParts  stable key components; {} hashed into the key
     */
    public function remember(string $bucket, array|string $keyParts, int $ttl, callable $callback): mixed
    {
        $hash = hash('sha256', is_array($keyParts) ? json_encode($keyParts) : $keyParts);

        return Cache::remember(
            "cache:{$bucket}:{$this->version($bucket)}:{$hash}",
            $ttl,
            $callback
        );
    }

    /**
     * Read (and compute) a value under a static key that never needs to be
     * invalidated individually (e.g. values sourced from server config).
     */
    public function rememberStatic(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember("cache:static:{$key}", $ttl, $callback);
    }

    /**
     * Invalidate one or more buckets by bumping their version counters so all
     * previously cached entries become stale on the next read.
     */
    public function invalidate(string ...$buckets): void
    {
        foreach ($buckets as $bucket) {
            Cache::increment($this->versionKey($bucket));
        }
    }

    private function version(string $bucket): int
    {
        return (int) Cache::rememberForever($this->versionKey($bucket), fn () => 1);
    }

    private function versionKey(string $bucket): string
    {
        return "cache:{$bucket}:version";
    }
}
