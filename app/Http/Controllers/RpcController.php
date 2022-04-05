<?php declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Middleware\{AuthenticateToken, CorsAllowUnconditional};
use App\Rpc\Dispatcher;
use Illuminate\Http\{Request, Response};

class RpcController extends Controller
{
    public function __construct(private Dispatcher $dispatcher)
    {
        $this->middleware(AuthenticateToken::class);
        $this->middleware(CorsAllowUnconditional::class);
    }

    public function handleCall(Request $request): Response
    {
        return $this->dispatcher->dispatch($request);
    }
}
