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

$app->get('/cron/kuorumberjalan',  function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    $db = $this->db;

    /** @var \LINE\LINEBot $bot */
    $bot = $this->bot;
    //Cek kuorum JIKA jam masih masuk jam kuorum
    if(date("H") >= 9 && date("H")<= 17) {
        $q = "SELECT * FROM `Current`";
        $stmt = $db->prepare($q);
        $stmt->execute();

        $count = $stmt->rowCount();
        if ($count <= KUORUM) {
            pushToAllGroups($bot, buildNotKuorumMessage($count));
        } elseif ($count < (KUORUM + SAFE_COUNT)) {
            pushToAllGroups($bot, new TextMessageBuilder("Mohon mengisi basecamp bagi yang tidak berhalangan."));
        }
    }
});

$app->get('/cron/kuorumharian',  function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    $db = $this->db;

    /** @var \LINE\LINEBot $bot */
    $bot = $this->bot;

    // Get the lower cap (yesterday at 00.00)
    $oDate = new DateTime();
    $oDate->modify('-1 day');
    $lowerCap = $oDate->format('Y-m-d 00:00:00');

    // Get the upper cap (yesterday at 23.59)
    $oDate = new DateTime();
    $oDate->modify('-1 day');
    $upperCap = $oDate->format('Y-m-d 23:59:59');

    $q = "SELECT COUNT(DISTINCT `nim`) AS count FROM Log WHERE DATE_ADD(jam_masuk, INTERVAL 5 HOUR) <= jam_keluar AND jam_masuk >= :lcap AND jam_keluar <= :ucap";
    $stmt = $db->prepare($q);
    $stmt->execute([':lcap' => $lowerCap, ':ucap' => $upperCap]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    $date = $oDate->format('d/m/Y');

    $messageBuilder = new MultiMessageBuilder();

    if($count >= KUORUM_HARIAN) {
        //TODO add lolos kuorum
        $messageBuilder->add(new ImagemapMessageBuilder(
            BASE_URL."/static/daily_kuorum",
            "[RANGKUMAN HARIAN]",
            new BaseSizeBuilder(419, 1040),
            [
                new ImagemapUriActionBuilder(
                    'https://osjurbot.didithilmy.com',
                    new AreaBuilder(0, 0, 1, 1)
                )
            ]
        ));
        $messageBuilder->add(new TextMessageBuilder("[RANGKUMAN HARIAN]\n\nStatus: Kuorum\n\nPada tanggal $date dari pukul 00:00 sampai 23:59, ada sebanyak $count orang berbeda yang datang ke basecamp selama lebih dari 5 jam. Persentase kehadiran adalah sebanyak ".(round($count/PERSONNEL_NO)*100)."%"));
    } else {
        //TODO add not lolos kuorum
        $messageBuilder->add(new ImagemapMessageBuilder(
            BASE_URL."/static/daily_nokuorum",
            "[RANGKUMAN HARIAN]",
            new BaseSizeBuilder(419, 1040),
            [
                new ImagemapUriActionBuilder(
                    'https://osjurbot.didithilmy.com',
                    new AreaBuilder(0, 0, 1, 1)
                )
            ]
        ));
        $messageBuilder->add(new TextMessageBuilder("[RANGKUMAN HARIAN]\n\nStatus: Tidak Kuorum\n\nPada tanggal $date dari pukul 00:00 sampai 23:59, ada sebanyak $count orang berbeda yang datang ke basecamp selama lebih dari 5 jam. Persentase kehadiran adalah sebanyak ".(round($count/PERSONNEL_NO)*100)."%"));
    }

    pushToAllGroups($bot, $messageBuilder);
});

$app->get('/cron/newbasecamp',  function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    pushToAllGroups($this->bot, getRandomBasecampMoveMeme());
});

$app->get('/debug/notifyAll',  function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    pushTextToAllIndividuals($this, "Halo, {name} ({nim})!");
});