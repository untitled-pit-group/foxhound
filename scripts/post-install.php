<?php declare(strict_types=1);
chdir(dirname(__DIR__));
if ( ! file_exists('.env')) {
    copy('.env.example', '.env');
    chmod('.env', 0600);
}
passthru('php artisan secret:generate');
