<?php

use App\Logging\CustomLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    'log_level' => env('LOG_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    | Levels: emergency, alert, critical, error, warning, notice, info, and debug
    |
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'slack']
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
            'permission' => 0774
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('SLACK_HOOK_URL'),
            'username' => 'teelaunch 2 Log',
            'emoji' => ':boom:',
            'level' => env('SLACK_LOG_LEVEL', 'error')
        ],

        // Note: Sentry is already reporting through App\Exceptions\Handler, using the sentry logger may cause exceptions to report twice,
        // remove implementation from App\Exceptions\Handler if using logger
        'sentry' => [
            'driver' => 'sentry',
            'level' => Monolog\Logger::ERROR, // The minimum monolog logging level at which this handler will be triggered // For example: `\Monolog\Logger::ERROR`
            'bubble' => true, // Whether the messages that are handled can bubble up the stack or not
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'orders' => [
            'driver' => 'custom',
            'folder' => 'orders',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'shipments' => [
            'driver' => 'custom',
            'folder' => 'shipments',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'stage-files' => [
            'driver' => 'custom',
            'folder' => 'stage-files',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'mockup-files' => [
            'driver' => 'custom',
            'folder' => 'mockup-files',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'print-files' => [
            'driver' => 'custom',
            'folder' => 'print-files',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'platform-products' => [
            'driver' => 'custom',
            'folder' => 'platform-products',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'images' => [
            'driver' => 'custom',
            'folder' => 'images',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'jobs' => [
            'driver' => 'custom',
            'folder' => 'jobs',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'etsy' => [
            'driver' => 'custom',
            'folder' => 'etsy',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'shopify' => [
            'driver' => 'custom',
            'folder' => 'shopify',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],

        'launch' => [
            'driver' => 'custom',
            'folder' => 'launch',
            'via' => \SunriseIntegration\TeelaunchModels\Utils\CustomLogger::class,
            'level' => env('LOG_LEVEL', 'info'),
            'permission' => 0774
        ],
    ],

];
