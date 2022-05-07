<?php declare(strict_types=1);
namespace App\Support\Db;
use App\Support\{Sha1Hash, Sha256Hash};
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Cast the string either to a {@link Sha256Hash} or a {@link Sha1Hash}, trying
 * them in that order.
 *
 * FIXME[pn]: This class exists purely for legacy purposes of pn's stupidity and
 * should be removed as soon as {@link Sha1Hash} is itself removed. The usage
 * of this cast should instead be converted into a StringableCast into
 * Sha256Hash directly.
 */
class HashCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        try {
            return Sha256Hash::fromString($value);
        } catch (\InvalidArgumentException $exc) {
            return Sha1Hash::fromString($value);
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        return $value->toString();
    }
}
