<?php declare(strict_types=1);
namespace App\Support\Postgres;

/**
 * Basic parser of Postgres's array syntax. Does not support dynamic delimiter
 * specification. Assumes that the input is reasonably well-formed, though
 * validates gross syntax violations.
 *
 * @see https://www.postgresql.org/docs/14/arrays.html#ARRAYS-IO
 */
class ArrayParser
{
    const
        OPEN_BRACE = '{',
        CLOSE_BRACE = '}',
        COMMA = ',',
        QUOTE = '"',
        BACKSLASH = "\\";

    protected int $i = 0;

    public function __construct(protected string $source) { }

    /**
     * Return the character at the cursor, or null if cursor is at
     * end-of-string.
     */
    protected function peek(): ?string
    {
        if ($this->i < strlen($this->source)) {
            return $this->source[$this->i];
        }
        return null;
    }

    protected function assertAt(string $char): void
    {
        $real = substr($this->source, $this->i, strlen($char));
        if ($real !== $char) {
            throw new ParseError($this->source, $this->i,
                "expected literal '{$char}'");
        }
    }

    protected function skipWhitespace(): void
    {
        // Whitespace isn't normally generated by Postgres but it can occur at
        // certain positions and should be ignored in that case.
        while ($this->i < strlen($this->source)) {
            $char = $this->source[$this->i];
            if ($char === ' ' || $char === "\t" || $char === "\n") {
                $this->i += 1;
            } else {
                break;
            }
        }
    }

    /**
     * Returns null if the current string is too short.
     */
    protected function take(int $length): ?string
    {
        if (strlen($this->source) < ($this->i + $length)) {
            return null;
        }
        $str = substr($this->source, $this->i, $length);
        $this->i += $length;
        return $str;
    }

    protected function takeSymbols(string $char): void
    {
        $this->assertAt($char);
        $this->i += strlen($char);
    }

    protected function takeQuotedValue(): string
    {
        $this->takeSymbols(self::QUOTE);
        $str = "";
        for (;;) {
            $str .= $this->takeSpan(self::BACKSLASH, self::QUOTE);
            if ($this->peek() === self::BACKSLASH) {
                $this->i += 1;
                $str .= $this->take(1);
                continue;
            } else {
                $this->takeSymbols(self::QUOTE);
                break;
            }
        }
        return $str;
    }

    /**
     * Take a substring out of source from cursor until the closest of the
     * provided delimiters. Advances the cursor until the closest delimiter.
     * Returns null if none of the delimiters can be found in the string between
     * the cursor and end-of-string.
     */
    protected function takeSpan(string ...$delimiters): ?string
    {
        $min = null;
        foreach ($delimiters as $delim) {
            $pos = strpos($this->source, $delim, $this->i);
            if ($pos !== false && ($min === null || $min > $pos)) {
                $min = $pos;
            }
        }
        if ($min === null) {
            return null;
        }

        $sub = substr($this->source, $this->i, $min - $this->i);
        $this->i = $min;
        return $sub;
    }

    /**
     * @return string|string[]|null
     */
    protected function takeValue()
    {
        if ($this->peek() === self::OPEN_BRACE) {
            return $this->takeArray();
        }
        if ($this->peek() === self::QUOTE) {
            return $this->takeQuotedValue();
        }
        $this->skipWhitespace();
        $span = $this->takeSpan(self::COMMA, self::CLOSE_BRACE,
            self::QUOTE, self::OPEN_BRACE);
        if ($span === null) {
            throw new ParseError($this->source, $this->i,
                "unexpected end-of-string in quoted value");
        }
        if ($this->peek() !== self::COMMA && $this->peek() !== self::CLOSE_BRACE) {
            throw new ParseError($this->source, $this->i,
                "unexpected value without delimiter from token");
        }

        // The above implicitly consumes any whitespace, so we trim it
        // explicitly.
        $span = rtrim($span, " \t\n");

        // Special case: Postgres serializes NULL values as unquoted `NULL`
        // literals. This handles that iff the literal was not quoted.
        if (strtoupper($span) === 'NULL') {
            return null;
        }
        return $span;
    }

    protected function takeArray(): array
    {
        $this->skipWhitespace();
        $this->takeSymbols(self::OPEN_BRACE);
        $array = array();
        $comma = null;

        while ($this->peek() !== self::CLOSE_BRACE) {
            $comma = null;
            $array[] = $this->takeValue();
            if ($this->peek() === self::COMMA) {
                $comma = $this->i;
                $this->takeSymbols(self::COMMA);
                // If the comma is immediately followed by closing brace, this
                // isn't technically valid Postgres array syntax, but it
                // doesn't really change anything if we let it slide since
                // under normal circumstances Postgres wouldn't generate such a
                // representation.
            }
            $this->skipWhitespace();
            if ($comma === null && $this->peek() !== self::CLOSE_BRACE) {
                throw new ParseError($this->source, $this->i,
                    "unexpected character");
            }
        }
        if ($comma !== null) {
            throw new ParseError($this->source, $comma,
                "value missing after delimiter");
        }

        $this->i += 1;
        return $array;
    }

    /**
     * @throws ParseError
     */
    public function parse(): array
    {
        return $this->takeArray();
    }
}
