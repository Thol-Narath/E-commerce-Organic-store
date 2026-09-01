<?php

namespace App\Http\Resources\Concerns;

trait FormatsMoney
{
    /**
     * Format a monetary value consistently as a two-decimal string.
     * Accepts decimal-cast strings, floats or ints.
     */
    protected function money(int|float|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}