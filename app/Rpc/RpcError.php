<?php declare(strict_types=1);
namespace App\Rpc;

class RpcError extends \RuntimeException
{
    // NOTE[pn]: May they forgive me the violation of the Liskov Substitution
    // Principle and trespassing of the SOLID for the sake of intuitiveness in
    // usage.
    public function __construct(int $code, string $message)
    {
        parent::__construct($message, $code);
    }
}
