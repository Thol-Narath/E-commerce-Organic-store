<?php

namespace Tests\Unit;

use App\Support\EnvFileEditor;
use PHPUnit\Framework\TestCase;

class EnvFileEditorTest extends TestCase
{
    private EnvFileEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = new EnvFileEditor;
    }

    public function test_parse_reads_values_and_strips_quotes(): void
    {
        $parsed = $this->editor->parse(
            "APP_NAME=Laravel\n".
            'MAIL_USERNAME="me@example.com"'."\n".
            "MAIL_PASSWORD='secret'\n".
            "# a comment line\n".
            "\n".
            "MAIL_PORT=587\n"
        );

        $this->assertSame('Laravel', $parsed['APP_NAME']);
        $this->assertSame('me@example.com', $parsed['MAIL_USERNAME']);
        $this->assertSame('secret', $parsed['MAIL_PASSWORD']);
        $this->assertSame('587', $parsed['MAIL_PORT']);
        $this->assertArrayNotHasKey('# a comment line', $parsed);
    }

    public function test_apply_replaces_existing_keys_and_keeps_the_rest(): void
    {
        $content = "APP_NAME=Laravel\nMAIL_HOST=mailpit\nDB_DATABASE=store\n";

        $updated = $this->editor->apply($content, [
            'MAIL_HOST' => 'smtp.gmail.com',
            'MAIL_PORT' => '587',
        ]);

        $this->assertStringContainsString('MAIL_HOST="smtp.gmail.com"', $updated);
        $this->assertStringContainsString('MAIL_PORT="587"', $updated);
        $this->assertStringContainsString('APP_NAME=Laravel', $updated);
        $this->assertStringContainsString('DB_DATABASE=store', $updated);
        $this->assertStringNotContainsString('mailpit', $updated);
    }

    public function test_apply_appends_missing_keys(): void
    {
        $updated = $this->editor->apply("APP_NAME=Laravel\n", [
            'MAIL_FROM_ADDRESS' => 'me@example.com',
        ]);

        $this->assertStringContainsString('MAIL_FROM_ADDRESS="me@example.com"', $updated);
    }

    public function test_password_with_dollar_is_single_quoted_and_left_literal(): void
    {
        $updated = $this->editor->apply("MAIL_PASSWORD=old\n", [
            'MAIL_PASSWORD' => 'pa$$word',
        ]);

        $this->assertStringContainsString("MAIL_PASSWORD='pa\$\$word'", $updated);
    }

    public function test_from_name_reference_is_preserved_with_double_quotes(): void
    {
        $updated = $this->editor->apply("MAIL_FROM_NAME=old\n", [
            'MAIL_FROM_NAME' => '${APP_NAME}',
        ]);

        $this->assertStringContainsString('MAIL_FROM_NAME="${APP_NAME}"', $updated);
    }

    public function test_set_writes_the_file_in_place(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "MAIL_HOST=mailpit\n");

        try {
            $this->editor->set($path, ['MAIL_HOST' => 'smtp.gmail.com']);

            $this->assertStringContainsString('MAIL_HOST="smtp.gmail.com"', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}