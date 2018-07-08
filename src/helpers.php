<?php
/**
 * Created by PhpStorm.
 * User: didithilmy
 * Date: 7/8/18
 * Time: 6:07 PM
 */


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

//Parameter
define("RESERVED_COUNT",15); //Orang yang terjadwal jika ada masalah pada ACTIVE_COUNT
define("ACTIVE_COUNT",45); //Orang yang akan dijadwalkan dataang
define("SAFE_COUNT",5); //Selisih antara jumlah yang ada dan kuorum yang masih aman
define("PERSONNEL_NO",197); // Jumlah seangkatan
define("KUORUM",30); //Minimal orang yang ada
define("KUORUM_HARIAN",PERSONNEL_NO/2); //Minimal orang yang ada dalam satu hari

define("SECRET","SECRETCODE");
define("BASE_URL","https://osjurbot.didithilmy.com/public");
define("INA_LOGIN_URL","https://login.itb.ac.id");

function login($db, $lineMid, $token) {

    $q = "SELECT * FROM `Users` WHERE `mid`=:mid";
    $stmt = $db->prepare($q);
    $stmt->execute([':mid' => $lineMid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nim = $result['nim'];

    $find = "SELECT * FROM `Current` WHERE `nim`=:nim";
    $stmt = $db->prepare($find);
    $stmt->execute([':nim' => $nim]);

    if($stmt->rowCount() > 0) {
        return "Kamu masih tercatat berada di basecamp. Logout terlebih dahulu. [plis jangan lupa logout kalo pulang]";
    }

    $sql = "INSERT INTO `Current`(`nim`, `jam_masuk`) VALUES (:nim, :jam_masuk)";

    $totp = TOTP::create(
        SECRET,
        TOTP_PERIOD // 5 menit
    );

    if(!$totp->verify($token)){
        return "Token yang kamu masukkan salah.";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':nim' => $nim, ':jam_masuk' => date("Y-m-d H:i:s")]);

    return "Selamat datang di basecamp! Kamu tercatat masuk jam ".date("H:i:s").".";
}

function logout($db, $bot, $lineMid, $token) {

    $q = "SELECT * FROM `Users` WHERE `mid`=:mid";
    $stmt = $db->prepare($q);
    $stmt->execute([':mid' => $lineMid]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    $nim = $userData['nim'];

    $find = "SELECT * FROM `Current` WHERE `nim`=:nim";
    $stmt = $db->prepare($find);
    $stmt->execute([':nim' => $nim]);

    $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);

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
    $stmt->execute([':nim' => $nim]);


    //Insert log
    $jamMasuk = $currentSession['jam_masuk'];

    $sql="INSERT INTO `Log`(`nim`,`jam_masuk`, `jam_keluar`) VALUES (:nim,:jam_masuk, :jam_keluar)";

    $stmt = $db->prepare($sql);
    $stmt->execute([':nim' => $nim,
        ':jam_masuk' => $jamMasuk,
        ':jam_keluar' => date("Y-m-d H:i:s")]);

    //add count
    $sql="UPDATE `Users` SET `count`=`count`+1 WHERE `nim` = :nim";
    $stmt = $db->prepare($sql);
    $stmt->execute([':nim' => $nim]);

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

    return "Selamat tinggal! Besok dateng lagi ya!!!!!!!!";
}

/** @var \LINE\LINEBot $bot */
function pushToAllGroups($bot, $messageBuilder) {
    $allowedGroupIds = explode(",", getenv("ALLOWED_GROUPS") ?: '');
    foreach($allowedGroupIds as $gid) {
        $bot->pushMessage($gid, $messageBuilder);
    }
}

function buildNotKuorumMessage($count) {
    $messageBuilder = new MultiMessageBuilder();
    $messageBuilder->add(new ImagemapMessageBuilder(
        BASE_URL."/static/nokuorum",
        "[BASECAMP TIDAK KUORUM!!!!!]",
        new BaseSizeBuilder(420, 1040),
        [
            new ImagemapUriActionBuilder(
                'https://osjurbot.didithilmy.com',
                new AreaBuilder(0, 0, 1, 1)
            )
        ]
    ));
    $messageBuilder->add(new TextMessageBuilder(getNotKuorumText($count)));
    $messageBuilder->add(new TextMessageBuilder("Saat ini Basecamp terisi $count orang."));

    return $messageBuilder;
}

function getNotKuorumText($count) {
    $messageAlternatives = array(
        "Dua tiga tong kosong\nBASECAMP LAGI KOSONG WOYY!",
        "Dua tiga nasi capcay\nBasecamp GAK kuorum WOYY!!",
        "Masa cuman $count orang di basecamp, arisan emak-emak aja lebih banyak!",
        "Tong kosong nyaring bunyinya\nBasecamp kosong nyaring sedihnya (cuman ada $count orang)",
        "WOI GAK KUORUM!! Cepet dateng ke basecamp. Sekarang cuman ada $count orang.",
        "$count orang yang ada di basecamp butuh kamu untuk memenuhi kuorum",
        "Dimana nih solidnya, masa di basecamp cuman $count orang",
        "Seharian berkelahi\nTentu tidak senang\nKuorum TIDAK terpenuhi\nCuman ada $count orang",
        "Ucup ikut diklat bacaman\nAsif magang di Tokopedia\nKita ini satu angkatan\nMasa kuorum aja gabisa??",
        "Pada suatu hari ada anak bernama Senna. Suatu ketika di hari yang indah, dia diamanahi untuk menjadi kortang oleh teman-teman seangkatannya. Dengan gagah berani Senna pun memberanikan diri untuk memenuhi amanah dari teman-temannya itu. Kerjaan yang dibebankan kepada dia tidaklah mudah, butuh banyak kesabaran untuk melakukannya, tapi tenang, teman-teman seangkatannya berkata siap membantunya.\n\nSalah satu tugasnya adalah mengkoordinasikan agar basecamp selalu memenuhi kuorum. Namun apa daya, Senna hanyalah manusia biasa yang tak luput dari kesalahan. Alhasil, sekarang hanya ada $count orang di Basecamp, dan ternyata \n\nBELUM MEMENUHI KUORUM!!\n\nAyolah bantu Senna kawan-kawan..",
        "Konon ceritanya terdapat sebuah kerajaan bernama UNIX. Kerajaan terbaik dan termakmur di seluruh alam Indah Tentram Bersinergi. Kerajaan indah ini dipimpin oleh seorang raja bernama Senna. Tetapi semua itu berubah saat kerajaan Mageria menyerang. Dengan serangan yang sangat mematikan yang membuat orang menjadi lemas dan malas hingga mati. Hanya dengan bersatu mereka dapat menyerang pasukan Mageria.\n\nWahai pasukan UNIX! Ayo kita bersatu melawan kerajaan Mageria dan buktikan kalau kita kerajaan terkuat! Jangan mau kalah, sejarah kita selama lebih dari 300 jam melewati berbagai rintangan tidak boleh menjadi sia-sia! BERSATULAH UNIX! JAYALAH UNIX!\nHanya dengan 30 orang bersemangat kita dapat menahan kekuatan kerajaan Mageria.\n\nAyooo baru $count orang yang berkumpul!!!\n\nUNIX HARUS MENANG"
    );

    // Buat opsi cerita panjang menjadi lebih jarang
    // Pesan singkat adalah < 200 karakter
    $messageProbs = array();
    foreach($messageAlternatives as $message) {
        // Jika pesan singkat, push 10 kali.
        if(strlen($message) <= 200) {
            for($i = 0; $i < 3; $i++)
                array_push($messageProbs, $message);
        } else {
            array_push($messageProbs, $message);
        }
    }

    return $messageProbs[rand(0, count($messageProbs) - 1)];
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