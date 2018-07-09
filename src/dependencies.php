<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

//database
$container['db'] = function($c) {
	$settings = $c->get('settings')['db'];
	$pdo = new PDO("mysql:host=".$settings['host'] . ";dbname=".$settings['dbname'], $settings['user'], $settings['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};

// LINE Bot Instance
$container['bot'] = function ($c) {
    $settings = $c->get('settings');
    $channelSecret = $settings['bot']['channelSecret'];
    $channelToken = $settings['bot']['channelToken'];
    $apiEndpointBase = $settings['apiEndpointBase'];
    $bot = new \LINE\LINEBot(new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channelToken), [
        'channelSecret' => $channelSecret,
        'endpointBase' => $apiEndpointBase, // <= Normally, you can omit this
    ]);
    return $bot;
};

// AMQP client
$container['amqp'] = function($c) {
    $settings = $c->get('settings');
    $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['user'], $settings['amqp']['password'], $settings['amqp']['vhost']);

    return $connection;
};