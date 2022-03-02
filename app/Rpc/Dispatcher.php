<?php declare(strict_types=1);
namespace App\Rpc;
use App\Http\ResponseSerializable;
use App\Support\Debug;
use Illuminate\Contracts\Support\{Arrayable, Jsonable};
use Illuminate\Container\Container;
use Illuminate\Http\{JsonResponse, Request, Response};
use JsonSerializable;

class Dispatcher
{
    public function __construct(
        private Registry $registry,
        private Container $container,
    ) {}

    private const JSON_ENCODE_OPTIONS = \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;
    private const JSON_MIME_TYPE = 'application/json; charset=UTF-8';

    private static function makeErrorResponse(
        $id,
        int $code,
        string $message,
        $data = null,
    ): Response {
        $json = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if ($id !== null) {
            $json['id'] = $id;
        }
        if ($data !== null) {
            $json['error']['data'] = $data;
        }

        if (2000 <= $code && $code < 3000) {
            $httpStatus = $code - 2000;
        } else {
            $httpStatus = Response::HTTP_BAD_REQUEST;
        }

        $json = json_encode($json, self::JSON_ENCODE_OPTIONS);
        return new Response($json, $httpStatus, [
            'Content-Type' => self::JSON_MIME_TYPE,
        ]);
    }

    private static function makeSuccessResponse($id, $data): Response
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $data,
        ], self::JSON_ENCODE_OPTIONS);
        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => self::JSON_MIME_TYPE,
        ]);
    }

    /**
     * @throws RpcError
     */
    private function preparseRequest(Request $req): array
    {
        $contentType = $req->getContentType();
        if ($contentType === null) {
            throw new RpcError(-32700, "Request body is empty.");
        }
        if ($contentType !== 'json') {
            throw new RpcError(-32700, "Request does not have a JSON Content-Type.");
        }

        $data = $req->getContent();
        try {
            $data = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING);
        } catch (\Exception $exc) {
            throw new RpcError(-32700, "Request is not properly-formed JSON.", $exc);
        }

        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new RpcError(-32600, "Request is not a JSON-RPC 2.0 request.");
        }

        return $data;
    }

    /**
     * @throws RpcError
     * @return [mixed,string,array]
     */
    private function extractDispatchInfo(array $request): array
    {
        if ( ! is_string($request['method'] ?? null)) {
            throw new RpcError(-32600, "Request does not specify a method.");
        }
        if ( ! is_array($request['params'] ?? null)) {
            throw new RpcError(-32600, "Request does not specify params.");
        }
        if (array_key_exists(0, $request['params'])) {
            throw new RpcError(-32600, "Params is not an object.");
        }
        return [$request['method'], $request['params']];
    }

    /**
     * @param ClosureHandler|ControllerHandler $handler
     * @return mixed
     */
    private function invoke($handler, array $params)
    {
        if ($handler instanceof ClosureHandler) {
            return ($handler->closure)($params);
        } else if ($handler instanceof ControllerHandler) {
            return $this->container->call('App\\Http\\Rpc\\' . $handler, [$params]);
        } else {
            throw new \LogicException("BUG: Invalid handler type: " . get_class($handler));
        }
    }

    public function dispatch(Request $req): Response
    {
        $id = null;
        $isNotification = false;

        try {
            try {
                $request = $this->preparseRequest($req);
            } catch (RpcError $err) {
                return self::makeErrorResponse(
                    null,
                    $err->getCode(),
                    $err->getMessage(),
                );
            }

            $id = $request['id'] ?? null;
            $isNotification = $id === null;

            [$method, $params] = $this->extractDispatchInfo($request);

            $handler = $this->registry->getHandler($method);
            if ($handler === null) {
                throw new RpcError(-32601, "Method does not exist.");
            }

            $result = $this->invoke($handler, $params);

            if ($isNotification) {
                return new Response('', Response::HTTP_NO_CONTENT);
            } else {
                return self::makeSuccessResponse($id, $result);
            }
        } catch (RpcError $err) {
            if ($isNotification) {
                return new Response('', Response::HTTP_NO_CONTENT);
            } else {
                return self::makeErrorResponse(
                    $id,
                    $err->getCode(),
                    $err->getMessage()
                );
            }
        } catch (\Throwable $exc) {
            if ($isNotification) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $data = null;
            if (\env('APP_DEBUG', false)) {
                $data = [
                    'exception' => get_class($exc),
                    'message' => $exc->getMessage(),
                    'location' => sprintf("%s(%d)",
                        $exc->getFile() ?: "<unknown>", $exc->getLine() ?: 0),
                    'trace' => Debug::prettyPrintTrace($exc->getTrace()),
                ];
            }
            return self::makeErrorResponse(
                $id,
                2500,
                "An internal server error occured.",
                $data,
            );
        }
    }
}
