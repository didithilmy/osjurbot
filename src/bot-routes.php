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

        //$replyText = $event->getText();
        //$logger->info('Reply text: ' . $replyText);
        //$resp = $bot->replyText($event->getReplyToken(), $replyText);
        //$logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
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
    }
}

function login($db, $lineMid, $token) {

    $q = "SELECT * FROM `Users` WHERE `mid`=:mid";
    $stmt = $db->prepare($q);
    $stmt->execute([':mid' => $lineMid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $uid = $result['nim'];

    $find = "SELECT * FROM `Current` WHERE `nim`=:nim";
    $stmt = $db->prepare($find);
    $stmt->execute([':nim' => $uid]);

    if($stmt->rowCount() > 0) {
        return "Kamu masih tercatat berada di basecamp. Logout terlebih dahulu. [plis jangan lupa logout kalo pulang]";
    }

    $sql = "INSERT INTO `Current`(`nim`) VALUES (:nim)";

    $totp = TOTP::create(
        SECRET,
        TOTP_PERIOD // 5 menit
    );

    if(!$totp->verify($token)){
        return "Token yang kamu masukkan salah.";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':nim' => $uid]);

    return "Selamat datang di basecamp! Kamu tercatat masuk jam ".date("h:i:s").".";
}

function logout($db, $bot, $lineMid, $token) {

    $q = "SELECT * FROM `Users` WHERE `mid`=:mid";
    $stmt = $db->prepare($q);
    $stmt->execute([':mid' => $lineMid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $uid = $result['nim'];

    $find = "SELECT * FROM `Current` WHERE `nim`=:nim";
    $stmt = $db->prepare($find);
    $stmt->execute([':nim' => $uid]);

    if($stmt->rowCount() == 0) {
        return "Kamu belum tercatat berada di basecamp. Ngapain logout ya?";
    }

    $sql = "DELETE FROM `Current` WHERE `nim`=:nim";

    $totp = TOTP::create(
        SECRET,
        TOTP_PERIOD // 5 menit
    );

    if(!$totp->verify($token)){
        return "Token yang kamu masukkan salah.";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':nim' => $uid]);


    //Cek kuorum JIKA jam masih masuk jam kuorum
    if(date("h") >= 9 && date("h")<= 17) {
        $q = "SELECT * FROM `Current`";
        $stmt = $db->prepare($q);
        $stmt->execute();

        $count = $stmt->rowCount();
        if ($count <= KUORUM) {
            pushToAllGroups($bot, new TextMessageBuilder(getNotKuorumText()));
        } elseif ($count < (KUORUM + SAFE_COUNT)) {
            pushToAllGroups($bot, new TextMessageBuilder("Mohon mengisi basecamp bagi yang tidak berhalangan."));
        }
    }

    return "Selamat tinggal! Besok dateng lagi ya!!!!!!!!";
}

function getNotKuorumText(){
    $i=mt_rand(0,4);
    $text = "";
    switch ($i) {
        case 0:
            $text = "Basecamp TIDAK kuorum, tolong segera mengisi basecamp bagi yang memungkinkan. Saat ini basecamp terisi $count orang.";
            break;
        case 1:
            $text = "WOI GAK KUORUM!! Cepet dateng ke basecamp. Sekarang cuman ada $count orang.";
            break;
        case 2:
            $text = "$count orang yang ada di basecamp butuh kamu untuk memenuhi kuorum"
            break;
        case 3:
            $text = "Gimana nih, masa di basecamp cuman $count orang.";
            break;
        case 4:
            $text = "Seharian berkelahi\nTentu tidak senang\nKuorum TIDAK terpenuhi\nCuman ada $count orang";
            break;
    }
    return $text;
}

/** @var \LINE\LINEBot $bot */
function pushToAllGroups($bot, $messageBuilder) {
    $allowedGroupIds = explode(",", getenv("ALLOWED_GROUPS") ?: '');
    foreach($allowedGroupIds as $gid) {
        $bot->pushMessage($gid, $messageBuilder);
    }
}

/** @var \LINE\LINEBot $bot */
function pushToAllIndividuals($db, $bot, $messageBuilder) {
    $q = "SELECT `mid` FROM `Users`";
    $stmt = $db->prepare($q);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($results as $row) {
        $bot->pushMessage($row['mid'], $messageBuilder);
    }
}
