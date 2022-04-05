<?php declare(strict_types=1);
namespace App\Support;

class Id
{
    const MIN = -9223372036854775807;
    const MAX = 9223372036854775807;
    public static function generate(): int
    {
        return random_int(self::MIN, self::MAX);
    }

    public static function encode(int $id): string
    {
        $id = pack('q', $id);
        return Base64Url::encode($id);
    }

    public static function decode(string $id): int
    {
        $rawId = Base64Url::decode($id);
        if ($rawId === null) {
            throw new \InvalidArgumentException("Invalid base64url encoding: {$id}");
        } else if (strlen($rawId) !== 8) {
            throw new \InvalidArgumentException("Invalid 64 ID: {$id}");
        }
        $id = unpack('q', $rawId)[1];
        return $id;
    }
}
