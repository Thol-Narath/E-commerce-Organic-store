<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use App\Models\Setting;
use App\Support\EnvFileEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Interactively configures SMTP email delivery in the .env file and
 * optionally sends a real test email through the new settings.
 *
 * Designed for the common Gmail case: it prompts (hidden) for the App
 * Password so it never has to be typed into a plain-text editor or shared.
 */
class ConfigureEmail extends Command
{
    protected $signature = 'email:configure
                            {--test= : Send a test email to this address after saving}
                            {--no-test : Skip the test email prompt}
                            {--password= : SMTP password / App Password (skips the hidden prompt, for automation)}';

    protected $description = 'Configure SMTP email delivery in .env (e.g. Gmail App Password) and optionally send a test email';

    public function handle(): int
    {
        $envPath = base_path('.env');
        $editor = new EnvFileEditor;

        if (! file_exists($envPath)) {
            $this->error(".env file not found at {$envPath}");

            return self::FAILURE;
        }

        $this->info('Configure email delivery (SMTP)');
        $this->line('For Gmail: enable 2-Step Verification, then create an App Password at');
        $this->line('https://myaccount.google.com/apppasswords');
        $this->newLine();

        $current = $editor->parse(file_get_contents($envPath));

        $host = (string) $this->ask('SMTP host', $current['MAIL_HOST'] ?: 'smtp.gmail.com');
        $port = (string) $this->ask('SMTP port', $current['MAIL_PORT'] ?: '587');
        $encryption = (string) $this->ask('Encryption (tls, ssl, or empty)', ($current['MAIL_ENCRYPTION'] ?? '') ?: 'tls');
        $username = (string) $this->ask('SMTP username (your email address)', $current['MAIL_USERNAME'] ?: ($current['MAIL_FROM_ADDRESS'] ?? ''));
        $optionPassword = $this->option('password');
        $usingOptionPassword = is_string($optionPassword) && $optionPassword !== '';
        $password = $usingOptionPassword
            ? $optionPassword
            : (string) $this->secret('SMTP password / Gmail App Password (input hidden)');

        if (! $usingOptionPassword) {
            $this->line('Received '.strlen($password).' character(s).');
        }

        $fromAddress = (string) $this->ask('From address', $username ?: ($current['MAIL_FROM_ADDRESS'] ?? ''));
        $fromName = (string) $this->ask('From name', $current['MAIL_FROM_NAME'] ?: config('app.name', 'Organic Store'));

        if ($password === '') {
            $this->error('Password cannot be empty. Nothing was changed.');

            return self::FAILURE;
        }

        // A Gmail App Password is exactly 16 letters/digits (Google displays it
        // as 4 groups of 4). Catch a truncated paste before it hits the server
        // as an opaque 535 "BadCredentials" error.
        if (! $usingOptionPassword && $this->isGmailHost($host) && ! $this->looksLikeAppPassword($password)) {
            $this->warn('This does not look like a Gmail App Password (expected 16 letters/digits).');
            $this->line('To generate one: enable 2-Step Verification, then visit https://myaccount.google.com/apppasswords');

            if (! $this->confirm('Save this password anyway?', false)) {
                $this->line('Aborted. Nothing was changed.');

                return self::FAILURE;
            }
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->error("From address '{$fromAddress}' is not a valid email. Nothing was changed.");

            return self::FAILURE;
        }

        $values = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $host,
            'MAIL_PORT' => $port,
            'MAIL_USERNAME' => $username,
            'MAIL_PASSWORD' => $password,
            'MAIL_ENCRYPTION' => $encryption,
            'MAIL_FROM_ADDRESS' => $fromAddress,
            'MAIL_FROM_NAME' => $fromName,
        ];

        $editor->set($envPath, $values);

        $this->call('config:clear');
        $this->newLine();
        $this->info('Saved to .env and cleared the config cache. Restart any running server.');

        $recipient = $this->resolveTestRecipient($fromAddress);

        if ($recipient === null) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Sending a test email to {$recipient} ...");

        // The current process loaded mail config at boot (before the .env
        // change), so apply the new values in-memory for this send.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $port,
            'mail.mailers.smtp.encryption' => $encryption !== '' ? $encryption : null,
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        $storeName = Setting::where('key', 'store.name')->value('value')
            ?: config('app.name', 'Organic Store');

        try {
            Mail::to($recipient)->send(new TestMail($storeName));
        } catch (Throwable $e) {
            $this->error('Test email failed: '.$e->getMessage());
            $this->line('The .env was saved, but delivery still fails. Double-check the App Password.');

            return self::FAILURE;
        }

        $this->info("Test email sent to {$recipient}. Check the inbox (and spam folder).");

        return self::SUCCESS;
    }

    /**
     * Decide whether to send a test email and to which address.
     * Returns null when testing is skipped.
     */
    private function resolveTestRecipient(string $fromAddress): ?string
    {
        if ($this->option('no-test')) {
            return null;
        }

        $option = $this->option('test');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $answer = trim((string) $this->ask('Send a test email to (leave blank to skip)', $fromAddress));

        return $answer !== '' ? $answer : null;
    }

    private function isGmailHost(string $host): bool
    {
        return str_contains(strtolower($host), 'gmail');
    }

    /**
     * Google App Passwords are 16 alphanumeric characters (spaces are display
     * only). Anything else is almost certainly a normal account password or a
     * truncated paste, which Gmail rejects.
     */
    private function looksLikeAppPassword(string $password): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{16}$/', str_replace(' ', '', $password));
    }
}