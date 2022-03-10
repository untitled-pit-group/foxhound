<?php declare(strict_types=1);
namespace Tests\Support;
use App\Support\Postgres\{ArrayParser, ParseError};

class PostgresArrayParserTest extends \PHPUnit\Framework\TestCase
{
    private function parse(string $text)
    {
        return (new ArrayParser($text))->parse();
    }

    private static function assertThrows(string $class, \Closure $invoke): void
    {
        try {
            $invoke();
        } catch (\Throwable $exc) {
            if ($exc instanceof $class) {
                self::assertTrue(true);
                return;
            }
            throw $exc;
        }
        self::fail("Expected {$class} to be thrown but " .
            "invocation succeeded instead.");
    }

    public function test_array_parser(): void
    {
        self::assertEquals([], $this->parse('{}'));
        self::assertEquals(['a'], $this->parse('{a}'));
        self::assertEquals(['a'], $this->parse('{"a"}'));
        self::assertEquals([null], $this->parse('{null}'));

        self::assertEquals(['a', 'b', 'c'], $this->parse('{a,b,c}'));
        self::assertEquals(['a', 'b'], $this->parse('{"a","b"}'));
        self::assertEquals(['a', 'b'], $this->parse('{a,"b"}'));
        self::assertEquals(['a', 'b'], $this->parse('{"a",b}'));
        self::assertEquals(['a,b'], $this->parse('{"a,b"}'));

        self::assertEquals(['a"b'], $this->parse('{"a\\"b"}'));
        self::assertEquals(["a\nb"], $this->parse("{\"a\nb\"}"));
        self::assertEquals(['a\\b'], $this->parse('{"a\\\\b"}'));
        self::assertEquals(['{a}'], $this->parse('{"{a}"}'));

        self::assertEquals(['a'], $this->parse(' { a } '));

        self::assertEquals([['a'], ['b']], $this->parse('{{a},{b}}'));
    }

    public function test_array_parser_errors(): void
    {
        // various incomplete array and string bounding syntaxes
        self::assertThrows(ParseError::class, fn() => $this->parse('a'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{a'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{{'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{"'));

        // non-naive delimiters
        self::assertThrows(ParseError::class, fn() => $this->parse('{"}'));

        // quoted string with escaped terminator quote
        self::assertThrows(ParseError::class, fn() => $this->parse('{"\\"}'));

        // trailing comma without following value
        self::assertThrows(ParseError::class, fn() => $this->parse('{a,}'));

        // two complete values without delimiting comma
        self::assertThrows(ParseError::class, fn() => $this->parse('{a"b"}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{"a"b}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{a{b}}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{{a}b}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{"a""b"}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{{a}"b"}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{"a"{b}}'));
        self::assertThrows(ParseError::class, fn() => $this->parse('{{a}{b}}'));
    }
}
