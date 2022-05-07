<?php declare(strict_types=1);
namespace App\Support\Db;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EnumCast implements CastsAttributes
{
    public function __construct(private string $enumClass) { }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        return ($this->enumClass)::from($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        return $value->value;
    }
}
