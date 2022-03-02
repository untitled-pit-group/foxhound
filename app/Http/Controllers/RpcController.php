<?php declare(strict_types=1);
namespace App\Http\Controllers;
use App\Rpc\Dispatcher;
use Illuminate\Http\{Request, Response};

class RpcController
{
    public function __construct(private Dispatcher $dispatcher)
    {
        // TODO: Register controller-scoped middleware for token verification.
    }

    public function handleCall(Request $request): Response
    {
        return $this->dispatcher->dispatch($request);
    }
}
