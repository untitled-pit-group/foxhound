<?php declare(strict_types=1);
namespace App\Models\FileIndexingState;

enum IndexingState: string
{
    case QUEUED = 'queued';
    case TRANSFORM = 'transform';
    case TRANSCRIBE = 'transcribe';
    case INGEST = 'ingest';
    case FINISHED = 'finished';
    case ERROR = 'error';
}
