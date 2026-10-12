<?php

namespace App\Support;

/**
 * Some Windows PHP builds ship openssl.cnf but never point PHP at it, which
 * makes EC key generation (and therefore VAPID key generation) fail with a bare
 * "Unable to create the key".
 *
 * This locates the config file and exports OPENSSL_CONF for the current process
 * so the rest of the app can rely on OpenSSL just working.
 */
class OpenSslConfig
{
    private static bool $resolved = false;

    public static function ensure(): void
    {
        if (self::$resolved) {
            return;
        }

        self::$resolved = true;

        if (function_exists('openssl_pkey_new') && ini_get('openssl.cafile')) {
            return;
        }

        foreach (self::candidates() as $candidate) {
            if (is_file($candidate)) {
                putenv('OPENSSL_CONF='.$candidate);
                @ini_set('openssl.cafile', $candidate);
                @ini_set('openssl.capath', dirname($candidate));

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function candidates(): array
    {
        $phpDir = dirname(PHP_BINARY);

        return [
            $phpDir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            $phpDir.DIRECTORY_SEPARATOR.'openssl.cnf',
            'C:\laragon\bin\php'.DIRECTORY_SEPARATOR.PHP_VERSION.DIRECTORY_SEPARATOR.'extras'
                .DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/etc/openssl.cnf',
        ];
    }
}
