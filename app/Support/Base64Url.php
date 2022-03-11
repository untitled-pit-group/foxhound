<?php declare(strict_types=1);
namespace App\Support;

class Base64Url
{
    public static function encode(string $text): string
    {
        $data = base64_encode($text);
        $data = strtr($data, '+/', '-_');
        return rtrim($data, '=');
    }

    public static function decode(string $data): ?string
    {
        $data = strtr($data, '-_', '+/');
        $text = base64_decode($data, strict: true);
        if ($text === false) {
            return null;
        }
        return $text;
    }
}
