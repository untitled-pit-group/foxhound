<?php declare(strict_types=1);
namespace App\Support;

/**
 * A {@link Sha1Hash} represents a SHA-1 hash of a file.
 */
class Sha1Hash
{
    private function __construct(
        /**
         * The hash in binary form. Exactly 20 characters long.
         */
        public readonly string $raw,
    ) { }

    public static function fromHex(string $hex): self
    {
        if (strlen($hex) !== 40 ||
            ($raw = hex2bin($hex)) === false ||
            strlen($raw) !== 20) {
            throw new \InvalidArgumentException();
        }
        return new self($raw);
    }

    public function hex(): string
    {
        return bin2hex($this->raw);
    }

    // MARK: StringableCast conformance
    public static function fromString(string $raw): self
    {
        if (strlen($raw) !== 20) {
            throw new \InvalidArgumentException();
        }
        return new self($raw);
    }
    public function toString(): string
    {
        return $this->raw;
    }
}
