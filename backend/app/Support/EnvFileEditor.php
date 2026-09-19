<?php

namespace App\Support;

/**
 * Small, dependency-free helper for reading and updating KEY=value pairs in a
 * .env file without disturbing comments, blank lines or key order.
 *
 * Values are quoted when written. Credentials are single-quoted so a password
 * containing "$" is never expanded as a variable reference; display values use
 * double quotes so references like "${APP_NAME}" keep working.
 */
class EnvFileEditor
{
    /**
     * Read simple KEY=value pairs, stripping surrounding quotes.
     *
     * @return array<string, string>
     */
    public function parse(string $content): array
    {
        $values = [];

        foreach (preg_split('/\r?\n/', $content) as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                $value = trim($matches[2]);

                if (preg_match('/^"(.*)"$/s', $value, $quoted) || preg_match("/^'(.*)'$/s", $value, $quoted)) {
                    $value = $quoted[1];
                }

                $values[$matches[1]] = $value;
            }
        }

        return $values;
    }

    /**
     * Replace each key in the content, or append it when missing.
     *
     * @param  array<string, string>  $values
     */
    public function apply(string $content, array $values): string
    {
        foreach ($values as $key => $value) {
            $replacement = $key.'='.$this->formatValue($key, $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => $replacement, $content, 1);
            } else {
                $content = rtrim($content, "\r\n").PHP_EOL.$replacement.PHP_EOL;
            }
        }

        return $content;
    }

    /**
     * Update the file at $path in place.
     *
     * @param  array<string, string>  $values
     */
    public function set(string $path, array $values): void
    {
        file_put_contents($path, $this->apply(file_get_contents($path), $values));
    }

    /**
     * Quote a value safely for a .env line.
     */
    protected function formatValue(string $key, string $value): string
    {
        if (in_array($key, ['MAIL_PASSWORD', 'MAIL_USERNAME'], true)) {
            return "'".str_replace("'", "\\'", $value)."'";
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}