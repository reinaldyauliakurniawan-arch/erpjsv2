<?php

use Monolog\Handler\FilterHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety of
    | powerful handlers and formatters that you're free to use.
    |
    | Production guidance:
    |   - Use the "daily" channel with JSON formatter so logs are queryable
    |     by log aggregators (ELK, Loki, Datadog, CloudWatch).
    |   - Keep 30 days of history (LOG_DAILY_DAYS).
    |   - Add the "stderr" channel for containerized deployments.
    |
    */

    'channels' => [

        // Stack: routes to channels listed in LOG_STACK (comma-separated).
        // In production, set LOG_STACK=daily,stderr for both file and stdout.
        'stack' => [
            'driver'            => 'stack',
            'channels'          => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        // Single file. Use for local dev only.
        'single' => [
            'driver'             => 'single',
            'path'               => storage_path('logs/laravel.log'),
            'level'              => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        // Daily rotation with JSON formatter — recommended for production.
        // Each day gets a new file: laravel-2025-06-23.log
        'daily' => [
            'driver'             => 'daily',
            'path'               => storage_path('logs/laravel.log'),
            'level'              => env('LOG_LEVEL', 'info'),
            'days'               => (int) env('LOG_DAILY_DAYS', 30),
            'replace_placeholders' => true,
            'formatter'          => JsonFormatter::class,
            'formatter_with'     => [
                'includeStacktraces' => env('LOG_STACKTRACES', false),
                'batchMode'          => JsonFormatter::BATCH_MODE_JSON,
            ],
            'processors'         => [
                PsrLogMessageProcessor::class,
                WebProcessor::class, // adds ip, url, referrer, user_agent
                [
                    'processor' => IntrospectionProcessor::class,
                    'options'   => ['level' => 'warning'],
                ],
            ],
        ],

        // stderr — for containerized deployments (Docker, Kubernetes).
        // Logs go to stdout, picked up by the container runtime.
        'stderr' => [
            'driver'             => 'monolog',
            'level'              => env('LOG_LEVEL', 'info'),
            'handler'            => StreamHandler::class,
            'handler_with'       => [
                'stream' => 'php://stderr',
            ],
            'formatter'          => JsonFormatter::class,
            'formatter_with'     => [
                'includeStacktraces' => env('LOG_STACKTRACES', false),
                'batchMode'          => JsonFormatter::BATCH_MODE_JSON,
            ],
            'processors'         => [
                PsrLogMessageProcessor::class,
                WebProcessor::class,
            ],
        ],

        // Slack channel — alert on critical errors (5xx, security events)
        'slack' => [
            'driver'             => 'slack',
            'url'                => env('LOG_SLACK_WEBHOOK_URL'),
            'username'           => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji'              => env('LOG_SLACK_EMOJI', ':boom:'),
            'level'              => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver'       => 'monolog',
            'level'        => env('LOG_LEVEL', 'debug'),
            'handler'      => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host'             => env('PAPERTRAIL_URL'),
                'port'             => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'             => 'syslog',
            'level'              => env('LOG_LEVEL', 'debug'),
            'facility'           => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver'             => 'errorlog',
            'level'              => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        // Null channel — for tests and suppressed deprecation warnings
        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Emergency channel — always writes to a single file, no matter what
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
