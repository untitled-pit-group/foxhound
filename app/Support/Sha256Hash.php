<?php declare(strict_types=1);
namespace App\Support;

/**
 * A {@link Sha1Hash} represents a SHA-256 hash of a file.
 */
class Sha256Hash
{
    private function __construct(
        /**
         * The hash in binary form. Exactly 20 characters long.
         */
        public readonly string $raw,
    ) { }

    public static function fromHex(string $hex): self
    {
        if (strlen($hex) !== 64 ||
            ($raw = hex2bin($hex)) === false ||
            strlen($raw) !== 32) {
            throw new \InvalidArgumentException();
        }
        return new self($raw);
    }

    public function hex(): string
    {
        return bin2hex($this->raw);
    }

    // MARK: StringableCast conformance
    public static function fromString($raw): self
    {
        // HACK[pn]: This should be refactored preferrably, this isn't exactly
        // a string by this point.
        $raw = stream_get_contents($raw);
        $raw = pg_unescape_bytea($raw);
        return new self($raw);
    }
    public function toString(): string
    {
        return '\x' . bin2hex($this->raw);
    }
}
