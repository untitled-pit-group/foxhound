<?php declare(strict_types=1);
namespace App\Support\Postgres;

class ParseError extends \RuntimeException
{
    public function __construct(
        private string $source,
        private int $position,
        private string $sourceMessage
    ) {
        $message = sprintf("%s (while parsing '%s' at position %d)",
            $this->sourceMessage, $this->source, $this->position);
        parent::__construct($message);
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSourceMessage(): string
    {
        return $this->sourceMessage;
    }
}
