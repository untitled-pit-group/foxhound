<?php declare(strict_types=1);
namespace App\Models\File;

enum FileType: string
{
    case PLAIN = 'plain';
    case DOCUMENT = 'document';
    case MEDIA = 'media';
}
