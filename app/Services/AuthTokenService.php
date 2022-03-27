<?php declare(strict_types=1);
namespace App\Services;
use App\Support\Base64Url;
use App\Support\Data\RedisConnection;
use Illuminate\Support\Facades\Redis;

class AuthTokenService
{
    public function __construct(private RedisConnection $redis) { }

    // NOTE: If any of these are changed, some or all of the issued auth tokens
    // will become invalid.
    const
        SELECTOR_LENGTH = 32,
        SELECTOR_STORAGE_PREFIX = "foxhound.auth-token.",
        VERIFIER_LENGTH = 32,
        VERIFIER_HASH_KEY_SUFFIX = "\0auth_token",
        VERIFIER_HASH_ALGO = 'sha512/256',
        TOKEN_VALIDITY_SECONDS = 60 * 60;

    public function mintToken(): string
    {
        $selector = random_bytes(self::SELECTOR_LENGTH);
        $verifier = random_bytes(self::VERIFIER_LENGTH);

        $key = env('APP_KEY') . self::VERIFIER_HASH_KEY_SUFFIX;
        $verifierHash = hash_hmac(self::VERIFIER_HASH_ALGO,
            $verifier, $key, binary: true);

        $this->redis->call('SETEX', self::SELECTOR_STORAGE_PREFIX . $selector,
            self::TOKEN_VALIDITY_SECONDS, $verifierHash);

        return Base64Url::encode($selector . $verifier);
    }

    /**
     * Returns {@code true} if the token is valid, {@code false} otherwise. If
     * the token is valid, refreshes its expiry to the configured validity
     * period.
     */
    public function verifyAndRefreshToken(string $token): bool
    {
        $rawData = Base64Url::decode($token);
        if ($rawData === null ||
            strlen($rawData) !== self::SELECTOR_LENGTH + self::VERIFIER_LENGTH) {
            return false;
        }
        $selector = substr($rawData, 0, self::SELECTOR_LENGTH);
        $verifier = substr($rawData, self::SELECTOR_LENGTH);

        $verifierHash = $this->redis->call('GETEX',
            self::SELECTOR_STORAGE_PREFIX . $selector,
             ex: self::TOKEN_VALIDITY_SECONDS);
        if ($verifierHash === null) {
            return false;
        }

        $key = env('APP_KEY') . self::VERIFIER_HASH_KEY_SUFFIX;
        $userVerifierHash = hash_hmac(self::VERIFIER_HASH_ALGO,
            $verifier, $key, binary: true);
        if ( ! hash_equals($verifierHash, $userVerifierHash)) {
            return false;
        }

        return true;
    }
}
