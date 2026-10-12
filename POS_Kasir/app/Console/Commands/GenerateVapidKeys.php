<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid-keys {--force : Regenerate even if keys already exist}';

    protected $description = 'Generate the VAPID key pair used for web push and store it in .env';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error('.env not found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($envPath);
        $hasKeys = str_contains($env, 'VAPID_PUBLIC_KEY=') && preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env) === 1;

        if ($hasKeys && ! $this->option('force')) {
            $this->warn('VAPID keys already present in .env. Use --force to replace them.');
            $this->line('  Replacing them invalidates every existing browser subscription.');

            return self::SUCCESS;
        }

        try {
            $keys = $this->generateKeys();
        } catch (Throwable $e) {
            $this->error('Gagal membuat kunci VAPID: '.$e->getMessage());
            $this->newLine();
            $this->warn('OpenSSL tidak menemukan berkas konfigurasi (openssl.cnf).');
            $this->line(' 、Windows PHP build: set OPENSSL_CONF lalu jalankan ulang, contoh:');
            $this->line('  $env:OPENSSL_CONF = "'.self::configHint().'"');
            $this->line('  php artisan push:vapid-keys');

            return self::FAILURE;
        }

        $env = preg_replace('/^VAPID_PUBLIC_KEY=.*$/m', 'VAPID_PUBLIC_KEY='.$keys['publicKey'], $env) ?? $env;
        $env = preg_replace('/^VAPID_PRIVATE_KEY=.*$/m', 'VAPID_PRIVATE_KEY='.$keys['privateKey'], $env) ?? $env;

        if (! str_contains($env, 'VAPID_PUBLIC_KEY=')) {
            $env .= PHP_EOL.'VAPID_PUBLIC_KEY='.$keys['publicKey'];
        }

        if (! str_contains($env, 'VAPID_PRIVATE_KEY=')) {
            $env .= PHP_EOL.'VAPID_PRIVATE_KEY='.$keys['privateKey'];
        }

        if (! str_contains($env, 'VAPID_SUBJECT=')) {
            $env .= PHP_EOL.'VAPID_SUBJECT=mailto:admin@tamanteras.id';
        }

        file_put_contents($envPath, trim($env).PHP_EOL);

        $this->info('VAPID keys written to .env');
        $this->line('  public : '.$keys['publicKey']);
        $this->line('  private: '.$keys['privateKey'].'  <- keep secret');
        $this->newLine();
        $this->comment('Run: php artisan config:clear');

        return self::SUCCESS;
    }

    /**
     * Generate the key pair.
     *
     * The OpenSSL extension resolves its configuration file when the process
     * starts, so putenv()/ini_set() at runtime has no effect on Windows builds
     * that ship openssl.cnf without registering it. When in-process generation
     * fails, retry in a child process that has OPENSSL_CONF set from the start.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    private function generateKeys(): array
    {
        try {
            return VAPID::createVapidKeys();
        } catch (Throwable $e) {
            $config = self::configHint();

            if (! is_file($config)) {
                throw $e;
            }

            $keys = $this->generateInSubprocess($config);

            if ($keys === null) {
                throw $e;
            }

            return $keys;
        }
    }

    /**
     * @return array{publicKey: string, privateKey: string}|null
     */
    private function generateInSubprocess(string $config): ?array
    {
        $script = <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
try {
    echo json_encode(\Minishlink\WebPush\VAPID::createVapidKeys());
} catch (\Throwable $e) {
    exit(1);
}
PHP;

        $tmp = tempnam(sys_get_temp_dir(), 'vapid').'.php';
        file_put_contents($tmp, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = array_merge(getenv(), ['OPENSSL_CONF' => $config]);

        $process = @proc_open(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmp),
            $descriptors,
            $pipes,
            base_path(),
            $env
        );

        if (! is_resource($process)) {
            @unlink($tmp);

            return null;
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($tmp);

        $decoded = json_decode(trim($stdout), true);

        return is_array($decoded) && isset($decoded['publicKey'], $decoded['privateKey']) ? $decoded : null;
    }

    /**
     * Best guess at the bundled openssl.cnf location.
     */
    public static function configHint(): string
    {
        $dir = dirname(PHP_BINARY);

        $candidates = [
            $dir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            $dir.DIRECTORY_SEPARATOR.'openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
