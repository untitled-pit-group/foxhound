<?php declare(strict_types=1);
namespace App\Support;
use Symfony\Component\HttpFoundation\Response;

class RpcConstants
{
    public const
        ERROR_SIZE_LIMIT_EXCEEDED = 1000,
        ERROR_IN_PROGRESS = 1001,
        ERROR_STATE = 1002,
        ERROR_SYNTAX = 1003;

    private const H = 2000; // short for HTTP
    public const
        ERROR_UNAUTHORIZED = self::H + Response::HTTP_UNAUTHORIZED,
        ERROR_NOT_FOUND = self::H + Response::HTTP_NOT_FOUND,
        ERROR_CONFLICT = self::H + Response::HTTP_CONFLICT;

    public const
        ERROR_INVALID_PARAMS = -32602;
}
