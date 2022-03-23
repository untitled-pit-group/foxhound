<?php declare(strict_types=1);

/** @var App\Rpc\Registry $rpc */

$rpc->register('test.hello_world', function (array $params) {
    return "hi!";
});

$rpc->register('uploads.begin', 'UploadController@begin');
