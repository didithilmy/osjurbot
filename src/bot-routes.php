<?php

use OTPHP\TOTP;
use Slim\Http\Request;
use Slim\Http\Response;
use \LINE\LINEBot\Constant\HTTPHeader;
use \LINE\LINEBot\Exception\InvalidSignatureException;
use \LINE\LINEBot\Exception\InvalidEventRequestException;
use \LINE\LINEBot\Event\MessageEvent;
use \LINE\LINEBot\Event\MessageEvent\TextMessage;

// Routes

$app->post('/webhook', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    /** @var \LINE\LINEBot $bot */
    $bot = $this->bot;
    /** @var \Monolog\Logger $logger */
    $logger = $this->logger;
    $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
    if (empty($signature)) {
        return $res->withStatus(400, 'Bad Request');
    }
    // Check request with signature and parse request
    try {
        $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
    } catch (InvalidSignatureException $e) {
        return $res->withStatus(400, 'Invalid signature');
    } catch (InvalidEventRequestException $e) {
        return $res->withStatus(400, "Invalid event request");
    }
    foreach ($events as $event) {
        if (!($event instanceof MessageEvent)) {
            $logger->info('Non message event has come');
            continue;
        }
        if (!($event instanceof TextMessage)) {
            $logger->info('Non text message has come');
            continue;
        }
        $replyText = $event->getText();
        $logger->info('Reply text: ' . $replyText);
        $resp = $bot->replyText($event->getReplyToken(), $replyText);
        $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }
    $res->write('OK');
    return $res;
});