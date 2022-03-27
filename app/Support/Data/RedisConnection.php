<?php declare(strict_types=1);
namespace App\Support\Data;
use PN\Redis\{Connection, ServerError, Status};

/**
 * A thin wrapper around {@link Connection} that connects to Redis lazily when
 * first invoked.
 */
class RedisConnection
{
    private array $config;
    private ?Connection $conn = null;

    public function __construct()
    {
        // TODO: This assumes that Redis is running on localhost. This should
        // probably be configurable?
        $this->config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 1,
        ];
    }

    protected function connect(): void
    {
        $this->conn = new Connection($this->config);
    }

    /**
     * Call the given command with the given args.
     *
     * If any of the args are keywords, they are sent in {@code $NAME $VALUE}
     * format, so that args like {@code EX <seconds>} can be idiomatically
     * supplied as {@code call(ex: 10)}.
     *
     * @throws ServerError
     * @return string|array|null
     */
    public function call(string $command, ...$args)
    {
        if ($this->conn === null) {
            $this->connect();
        }
        $result = $this->conn->call($command, ...$args);
        if (is_object($result) && $result instanceof Status) {
            $result = (string) $result;
        }
        return $result;
    }
}
