<?php

namespace App\Support;

use App\Models\Setting;

class QrisHelper
{
    public const DEFAULT_STATIC_QRIS = '00020101021126610014COM.GO-JEK.WWW01189360091432738558080210G2738558080303UMI51440014ID.CO.QRIS.WWW0215ID10265748298200303UMI5204581253033605802ID5921Taman Teras, MAMAJANG6008MAKASSAR61059013562070703A016304D51B';

    /**
     * Get the configured static QRIS payload, falling back to the built-in default.
     */
    public static function getStaticPayload(): string
    {
        return trim(Setting::get('qris_static_payload', self::DEFAULT_STATIC_QRIS) ?? self::DEFAULT_STATIC_QRIS);
    }

    /**
     * Persist the static QRIS payload.
     */
    public static function setStaticPayload(string $payload): bool
    {
        $payload = trim($payload);

        if ($payload === '') {
            return false;
        }

        Setting::put('qris_static_payload', $payload);

        return true;
    }

    /**
     * CRC16-CCITT (False) checksum used by the EMVCo / QRIS standard.
     */
    public static function calculateCRC16(string $str): string
    {
        $crc = 0xFFFF;
        $strlen = strlen($str);

        for ($c = 0; $c < $strlen; $c++) {
            $crc ^= (ord($str[$c]) << 8);

            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Convert a static QRIS string into a dynamic one carrying the exact amount.
     */
    public static function convertStaticToDynamic(?string $staticQris, float|int|string $amount): string
    {
        $staticQris = trim($staticQris ?: self::getStaticPayload());

        if ($staticQris === '') {
            return '';
        }

        // 1. Strip any existing checksum.
        $pos6304 = strrpos($staticQris, '6304');
        $qris = $pos6304 !== false ? substr($staticQris, 0, $pos6304) : substr($staticQris, 0, -4);

        // 2. Static (010211) -> Dynamic (010212) point of initiation.
        if (strpos($qris, '010211') !== false) {
            $qris = str_replace('010211', '010212', $qris);
        }

        // 3. Inject tag 54 (transaction amount) before the country code when present.
        $nominal = (string) intval(round((float) $amount));
        $nominalLength = str_pad((string) strlen($nominal), 2, '0', STR_PAD_LEFT);
        $tag54 = '54'.$nominalLength.$nominal;

        if (strpos($qris, '5802ID') !== false) {
            $parts = explode('5802ID', $qris);
            $qris = $parts[0].$tag54.'5802ID'.$parts[1];
        } else {
            $qris .= $tag54;
        }

        // 4. Append the checksum tag and its value.
        $qris .= '6304';

        return $qris.self::calculateCRC16($qris);
    }

    /**
     * Extract the merchant fields (TLV tags 59/60/61) from a QRIS string.
     */
    public static function parseQrisDetails(string $qris): array
    {
        $details = [
            'merchant_name' => '',
            'merchant_city' => '',
            'postal_code' => '',
            'is_dynamic' => false,
            'amount' => null,
        ];

        if ($qris === '') {
            return $details;
        }

        if (strpos($qris, '010212') !== false) {
            $details['is_dynamic'] = true;
        }

        $idx = 0;
        $len = strlen($qris);

        while ($idx < $len - 4) {
            $tag = substr($qris, $idx, 2);
            $valLen = (int) substr($qris, $idx + 2, 2);
            $val = substr($qris, $idx + 4, $valLen);
            $idx += 4 + $valLen;

            match ($tag) {
                '59' => $details['merchant_name'] = $val,
                '60' => $details['merchant_city'] = $val,
                '61' => $details['postal_code'] = $val,
                '54' => $details['amount'] = (float) $val,
                default => null,
            };
        }

        return $details;
    }

    /**
     * Remote QR image URL for the given payload.
     */
    public static function getQrImageUrl(string $data, int $size = 280): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.urlencode($data);
    }
}
