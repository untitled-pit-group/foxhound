<?php declare(strict_types=1);
namespace App\Support;

class Arr
{
    public static function any(array $array, \Closure $predicate): bool
    {
        foreach ($array as $element) {
            if ($predicate($element)) {
                return true;
            }
        }
        return false;
    }
}
