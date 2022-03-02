<?php declare(strict_types=1);
namespace App\Rpc;

class ClosureHandler
{
    public function __construct(public readonly \Closure $closure) {}
}
