<?php declare(strict_types=1);
namespace App\Http\Controllers;
use App\Services\AuthTokenService;
use Illuminate\Http\{Request, Response};

class TokenController extends Controller
{
    public function __construct(private AuthTokenService $authTokens) { }

    public function mintToken(Request $request): Response
    {
        $content = $request->getContent();
        if ( ! is_string($content)) {
            return new Response(
                "The request didn't contain the application secret.\n",
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $secret = (string) env('FOXHOUND_GLOBAL_SECRET');
        if ($secret === "") {
            return new Response(
                "The server is misconfigured and doesn't have an application " .
                    "secret set.\n",
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if ( ! hash_equals($secret, $content)) {
            return new Response(
                "The provided application secret is invalid.\n",
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $token = $this->authTokens->mintToken();
        return new Response($token . "\n", Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
