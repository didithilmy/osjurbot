<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/addNama', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/listBasecamp', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/sampai', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/pulang', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/listCurrent', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/tambahBlacklist', function (Request $request, Response $response, array $args) {

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
