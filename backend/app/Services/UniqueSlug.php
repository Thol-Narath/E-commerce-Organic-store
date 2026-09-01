<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates unique slugs for Eloquent models that have a `slug` column.
 */
trait UniqueSlug
{
    /**
     * Generate a unique slug for the given model, appending a numeric suffix
     * when a collision occurs. Optionally ignores a model by id (for updates).
     *
     * @param  class-string<Model>  $model
     */
    public function uniqueSlug(string $model, string $name, ?string $explicit = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($explicit ?: $name);
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $suffix = 2;

        while ($model::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
