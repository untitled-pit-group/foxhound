<?php declare(strict_types=1);
namespace App\Http\Middleware;
use Illuminate\Http\{Request, Response};

/**
 * Add CORS headers to the requester origin unconditionally.
 */
class CorsAllowUnconditional
{
    public function handle(Request $request, \Closure $next, ?string $guard = null)
    {
        $response = $next($request);
        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin',
                $request->headers->get('Origin'));
        }
        return $response;
    }
}
