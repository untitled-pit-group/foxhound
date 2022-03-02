<?php declare(strict_types=1);
namespace App\Rpc;

class ControllerHandler
{
    public function __construct(public readonly string $target) {}
}
