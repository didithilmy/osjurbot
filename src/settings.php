<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // DB setting
        'db' => [
            'host' => getenv('DB_HOST'),
            'dbname' => getenv('DB_NAME'),
            'user' => getenv('DB_USERNAME'),
            'pass' => getenv('DB_PASSWORD')
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // LINE Bot instance
        'bot' => [
            'channelToken' => getenv('LINEBOT_CHANNEL_TOKEN') ?: '',
            'channelSecret' => getenv('LINEBOT_CHANNEL_SECRET') ?: '',
        ],

        // LINE Bot instance
        'amqp' => [
            'host' => getenv('AMQP_HOST') ?: 'localhost',
            'port' => getenv('AMQP_PORT') ?: 5672,
            'user' => getenv('AMQP_USER') ?: 'guest',
            'password' => getenv('AMQP_PASSWORD') ?: 'guest',
            'vhost' => getenv('AMQP_VHOST') ?: '/',
        ],
    ],
];
