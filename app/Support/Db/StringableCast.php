<?php declare(strict_types=1);
namespace App\Support\Db;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Cast the given attribute from a string into a class. The class must abide
 * with the ad-hoc contract of having a {@code static fromString(string): self}
 * factory constructor and a {@code toString(): string} method.
 */
class StringableCast implements CastsAttributes
{
    public function __construct(private string $class) { }

    public function get($model, string $key, $value, array $attributes)
    {
        return ($this->class)::fromString($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value->toString();
    }
}
