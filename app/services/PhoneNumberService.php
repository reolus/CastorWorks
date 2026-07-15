<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PhoneNumberService
{
    public static function normalize(string $number, string $defaultCountryCode = '1'): string
    {
        $number = trim($number);
        if ($number === '') {
            throw new RuntimeException('A mobile number is required.');
        }
        $leadingPlus = str_starts_with($number, '+');
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if ($digits === '') {
            throw new RuntimeException('The mobile number is invalid.');
        }
        if (!$leadingPlus && strlen($digits) === 10) {
            $digits = $defaultCountryCode . $digits;
        } elseif (!$leadingPlus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new RuntimeException('The mobile number must be a valid E.164 number.');
        }
        return '+' . $digits;
    }

    public static function smsParts(string $text): array
    {
        $gsm = preg_match('/^[\x{000A}\x{000D}\x{0020}-\x{007E}£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà]*$/u', $text) === 1;
        $single = $gsm ? 160 : 70;
        $multi = $gsm ? 153 : 67;
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        $parts = $length <= $single ? 1 : (int) ceil($length / $multi);
        return ['encoding' => $gsm ? 'GSM-7' : 'UCS-2', 'characters' => $length, 'parts' => max(1, $parts)];
    }
}
