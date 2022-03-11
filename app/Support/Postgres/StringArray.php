<?php declare(strict_types=1);
namespace App\Support\Postgres;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class StringArray implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        return (new ArrayParser($value))->parse();
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return ArrayParser::encodeArray($value);
    }
}
