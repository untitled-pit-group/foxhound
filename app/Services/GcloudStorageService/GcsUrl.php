<?php declare(strict_types=1);
namespace App\Services\GcloudStorageService;

/**
 * A canonical decomposition of a {@code gs://} URL into its constituent parts.
 */
class GcsUrl
{
    public readonly string $bucket;
    public readonly string $object;

    /**
     * Verify that the provided URL is correct: the schema is {@code gs}, and
     * it contains both a host and a path part. Query, port, userinfo, and
     * fragment parts are discarded.
     */
    public function __construct(string $url)
    {
        $urlParts = parse_url($url);
        if ($urlParts === false || ($urlParts['scheme'] ?? null) !== 'gs') {
            throw new \InvalidArgumentException("Invalid GCS URL: {$url}");
        }
        $host = $urlParts['host'] ?? null;
        $path = $urlParts['path'] ?? null;
        if ($host === null || $path === null) {
            throw new \InvalidArgumentException("Invalid GCS URL: {$url}");
        }
        $this->bucket = $host;
        $this->object = ltrim($path, '/');
    }

    public function __toString(): string
    {
        return "gs://{$this->bucket}/{$this->object}";
    }
}
