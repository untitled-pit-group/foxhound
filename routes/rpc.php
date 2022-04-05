<?php declare(strict_types=1);

/** @var App\Rpc\Registry $rpc */

$rpc->register('test.hello_world', function (array $params) {
    return "hi!";
});

(function () use ($rpc) {
    $methods = [
        'uploads.begin' => 'UploadController@begin',
        'uploads.cancel' => 'UploadController@cancel',
        'uploads.finish' => 'UploadController@finish',
        'uploads.report_progress' => 'UploadController@reportProgress',
        'uploads.progress' => 'UploadController@getProgress',
        'files.list' => 'FileController@listFiles',
        'files.check_indexing_progress' => 'FileController@checkIndexingProgress',
        'files.get_indexing_error' => 'FileController@getIndexingError',
        'files.get' => 'FileController@getFile',
        'files.request_download' => 'FileController@requestDownload',
        'files.edit' => 'FileController@editFile',
        'files.edit_tags' => 'FileController@editTags',
        'search.perform' => 'SearchController@performSearch',
    ];
    foreach ($methods as $name => $handler) {
        $rpc->register($name, $handler);
    }
})();
