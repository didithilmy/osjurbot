<?php

use OTPHP\TOTP;
use Slim\Http\Request;
use Slim\Http\Response;
use \LINE\LINEBot\Constant\HTTPHeader;
use \LINE\LINEBot\Exception\InvalidSignatureException;
use \LINE\LINEBot\Exception\InvalidEventRequestException;
use \LINE\LINEBot\Event\MessageEvent;
use \LINE\LINEBot\Event\MessageEvent\TextMessage;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder;
use \LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder;
use \LINE\LINEBot\ImagemapActionBuilder\ImagemapUriActionBuilder;
use \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder;

define("LIFF_ID_ASSOCINA", getenv("LIFF_ASSOCIATE_INA") ?: '1592475912-K596Y0YE');
define("TOTP_PERIOD", getenv("TOTP_PERIOD") ?: 300);
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

        processText($this->db, $bot, $event);
    }
    $res->write('OK');
    return $res;
});

$app->get('/debug/totp', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    $totp = TOTP::create(
        SECRET,
        TOTP_PERIOD // 5 menit
    );
    return $res->write($totp->now());
});

/** @var \LINE\LINEBot\Event\MessageEvent\TextMessage $event */
function processText($db, $bot, $event) {
    $allowedGroupIds = explode(",", getenv("ALLOWED_GROUPS") ?: '');

    $text = $event->getText();
    $replyToken = $event->getReplyToken();
    $words = explode(" ", $text);
    $firstWord = strtolower($words[0]);

    /** @var \LINE\LINEBot $bot */

    switch ($firstWord) {
        case "/uid":
            $uid = $event->getUserId();
            $bot->replyText($replyToken, $uid);
            break;
        case "/gid":
            if($event->isUserEvent()) {
                $uid = $event->getUserId();
            } else if($event->isGroupEvent()) {
                $uid = $event->getGroupId();
            } else if($event->isRoomEvent()) {
                $uid = $event->getRoomId();
            } else {
                $uid = "UNKNOWN";
            }

            /** @var \LINE\LINEBot $bot */

            $bot->replyText($replyToken, $uid);
            break;
        case "/userinfo":
            if($event->isUserEvent()) {
                $bot->replyText($replyToken, "Development on progress");
            }
            break;
        case "login":
            if($event->isUserEvent()) {
                try {
                    $status = login($db, $event->getUserId(), $words[1]);
                    $bot->replyText($replyToken, $status);
                } catch (PDOException $e) {
                    $bot->replyText($replyToken, "Unexpected error [PDOException]");
                }
            }
            break;
        case "logout":
            if($event->isUserEvent()) {
                try {
                    $status = logout($db, $bot, $event->getUserId(), $words[1]);
                    $bot->replyText($replyToken, $status);
                } catch (PDOException $e) {
                    $bot->replyText($replyToken, "Unexpected error [PDOException]");
                }
            }
            break;
        case "assoc":
        case "ina":
            if($event->isUserEvent()) {
                $messageBuilder = new MultiMessageBuilder();
                $messageBuilder->add(new TextMessageBuilder("Halo! Klik tombol di bawah ini ya buat nyambungin akun INA kamu"));
                $messageBuilder->add(new ImagemapMessageBuilder(
                    BASE_URL."/static/associna",
                    "Sambungkan akun INA kamu",
                    new BaseSizeBuilder(466, 1040),
                    [
                        new ImagemapUriActionBuilder(
                            'line://app/'.LIFF_ID_ASSOCINA,
                            new AreaBuilder(0, 0, 1040, 466)
                        )
                    ]
                ));
                $messageBuilder->add(new TextMessageBuilder("Apabila tombol diatas tidak berfungsi, coba buka ".BASE_URL.'/user/associateManual?userId='.$event->getUserId()));
                $bot->replyMessage($replyToken, $messageBuilder);
            }
            break;
        case "/status":
            if($event->isGroupEvent()) {
                if(in_array($event->getGroupId(), $allowedGroupIds)) {
                    $q = "SELECT * FROM `Current`";
                    $stmt = $db->prepare($q);
                    $stmt->execute();

                    $count = $stmt->rowCount();

                    $bot->replyText($replyToken, "Saat ini basecamp terisi $count orang.");
                } else {
                    $bot->replyText($replyToken, "Unauthorized.");
                    $bot->leaveGroup($event->getGroupId());
                }
            }
            break;
        case "/basecamp-moved":
            $bot->replyMessage($replyToken, getRandomBasecampMoveMeme());
            break;
    }
}