<?php declare(strict_types=1);
namespace App\Rpc;

/**
 * Holds associations between method names and their handlers.
 *
 * Handlers are provided as Closures or callables, in which case they shall be
 * called with their arguments as per the request and their return value
 * serialized, or as strings of form `ControllerClass@method` which will be
 * resolved similarly to Laravel's own routers onto controller objects that are
 * located with App\Http\Rpc.
 */
class Registry
{
    private $handlers = [ ];

    /**
     * @param Closure|array|string $handler
     */
    public function register(string $methodName, $handler): void
    {
        if (is_string($handler)) {
            $this->handlers[$methodName] = new ControllerHandler($handler);
            return;
        }
        if (is_array($handler)) {
            $handler = \Closure::fromCallable($handler);
        }
        if ($handler instanceof \Closure) {
            $this->handlers[$methodName] = new ClosureHandler($handler);
            return;
        }
        throw new \InvalidArgumentException(
            '$handler must be Closure, array, or string');
    }

    /**
     * @return ClosureHandler|ControllerHandler|null
     */
    public function getHandler(string $methodName)
    {
        return $this->handlers[$methodName] ?? null;
    }
}
