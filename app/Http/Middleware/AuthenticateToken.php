<?php declare(strict_types=1);
namespace App\Http\Middleware;
use App\Services\AuthTokenService;
use Illuminate\Http\{Request, Response};

class AuthenticateToken
{
    public function __construct(AuthTokenService $authTokens) { }

    private static function errorResponse(Request $request): Response
    {
        $id = null;
        if ($request->isJson()) {
            try {
                $data = $request->json();
            } catch (\Throwable $exc) {
                // NOTE[pn]: There's no protection in Laravel internals against
                // malformed (say, non-array) JSON being submitted with a JSON
                // mimetype. So we gotta do the guard ourselves.
                $data = null;
            }
            if (is_array($data) && array_key_exists('id', $data)) {
                $id = $data['id'];
                if (!is_string($id) && !is_numeric($id)) {
                    // Non-primitive IDs are invalid as per JSON-RPC. Also we
                    // don't send booleans around here.
                    $id = null;
                }
            }
        }
        return new Response(
            json_encode([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => 2401,
                    'message' => "The request didn't contain a required " .
                        "security token, or the token provided has expired. " .
                        "Please try restarting the app.",
                ],
            ], \JSON_UNESCAPED_SLASHES),
            Response::HTTP_UNAUTHORIZED,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        );
    }

    public function handle(Request $request, \Closure $next, ?string $guard = null)
    {
        $token = $request->headers->get('Authorization');
        if ($token === null) {
            return self::errorResponse($request);
        }
        $parts = explode(' ', $token, 2);
        if (count($parts) < 2 || $parts[0] != 'Bearer') {
            return self::errorResponse($request);
        }
        return $next($request);
    }
}
