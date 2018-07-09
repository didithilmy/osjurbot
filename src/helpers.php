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
use \LINE\LINEBot\MessageBuilder\LocationMessageBuilder;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//Parameter
define("RESERVED_COUNT",15); //Orang yang terjadwal jika ada masalah pada ACTIVE_COUNT
define("ACTIVE_COUNT",45); //Orang yang akan dijadwalkan dataang
define("SAFE_COUNT",5); //Selisih antara jumlah yang ada dan kuorum yang masih aman
define("PERSONNEL_NO",197); // Jumlah seangkatan
define("KUORUM",30); //Minimal orang yang ada
define("KUORUM_HARIAN",PERSONNEL_NO/2); //Minimal orang yang ada dalam satu hari

define("SECRET", getenv("TOTP_SECRET") ?: "SECRETCODE");
define("BASE_URL", getenv("BASE_URL") ?: "https://osjurbot.didithilmy.com/public");
define("INA_LOGIN_URL", getenv("INA_LOGIN_URL") ?: "https://login.itb.ac.id");

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
        "Gak tahu sih kalo kalian, tapi kalo aku inget sejarah Indonesia. Beratus-ratus tahun dijajah sama Belanda lalu Jepang. Kadang greget kenapa gak bersatu dari awal lawan penjajah, padahal kalo semua bersatu bisa menang pasti. Belum lagi perjuangan yang hanya ngandalin pemimpin, saat pemimpinnya tidak ada, langsung bubar.\n\nAkan tetapi setelah melihat basecamp UNIX. Ternyata memang susah ya ngumpulin orang. Sehebat apapun Senna, tanpa kita dia tidak ada apa-apa. Butuh persatuan dari seluruh massa UNIX agar kita menang! Agar UNIX berjaya! Masa mau menang dengan cuman $count orang. \n\nAyo semua kumpul, kita berjuang bersama!",
        "Pada suatu hari, ada seorang gadis berbaju merah yang sedang berkelana di hutan. Dia baru saja pulang dari tengah hutan untuk memetik satu keranjang apel. Tiba-tiba, terdengar langkah kaki yang membuatnya ketakutan. Sontak dia langsung berlari menjauh dari sumber suara itu. Sekian ratus langkah kemudian, gadis tadi kelelahan dan tersesat. Karena hari sudah gelap, dia memutuskan untuk beristirahat di atas pohon.\n\nTepat pukul jam 12 malam, suara langkah kaki tadi terdengar lagi. Dengan ketakutan dia menuruni pohon untuk berlari. Seketika dia turun, muncul sosok berbadan besar dari kegelapan. Jantungnya berdegup. Bulu kuduknya berdiri. Dalam sekejap mimpi buruknya menjadi kenyataan. Gadis itu berlari tanpa arah di hutan, berharap bisa menjauh dari sosok tersebut. Sayangnya, gadis itu tidak berlari cukup cepat untuk bisa kabur dari jeratan sosok tersebut. Dia berteriak ketakutan, kemudian dia menggigit lengan sosok besar itu.\n\nDia berlari lagi, tanpa arah dan tidak berdaya, sampat suatu saat terjadi sebuah keajaiban. Tepat di depan matanya, ada sebuah tempat singgah yang aman dari sosok besar tersebut. Dia segera berlari masuk ke tempat tersebut, dan ternyata tempat itu adalah...\n\nBASECAMP UNIX 2017!!\n\nDi sana dia bertemu dengan orang-orang yang siap menemani perjalanannya di hutan untuk pulang. Diantarkanlah gadis tersebut pulang oleh teman barunya, dan saat dia hendak tidur di kasur empuknya, dia terbangun. Ternyata semua itu hanyalah mimpi belaka. Dia kecewa karena teman-teman barunya itu fana. Tapi apa daya..\n\nBASECAMP UNIX 2017 GAK KUORUM GAES!!!\n\nMakanya, datang ya!",
        "Pada suatu hari ada anak bernama Senna. Suatu ketika di hari yang indah, dia diamanahi untuk menjadi kortang oleh teman-teman seangkatannya. Dengan gagah berani Senna pun memberanikan diri untuk memenuhi amanah dari teman-temannya itu. Kerjaan yang dibebankan kepada dia tidaklah mudah, butuh banyak kesabaran untuk melakukannya, tapi tenang, teman-teman seangkatannya berkata siap membantunya.\n\nSalah satu tugasnya adalah mengkoordinasikan agar basecamp selalu memenuhi kuorum. Namun apa daya, Senna hanyalah manusia biasa yang tak luput dari kesalahan. Alhasil, sekarang hanya ada $count orang di Basecamp, dan ternyata \n\nBELUM MEMENUHI KUORUM!!\n\nAyolah bantu Senna kawan-kawan..",
        "Konon ceritanya terdapat sebuah kerajaan bernama UNIX. Kerajaan terbaik dan termakmur di seluruh alam Indah Tentram Bersinergi. Kerajaan indah ini dipimpin oleh seorang raja bernama Senna. Tetapi semua itu berubah saat kerajaan Mageria menyerang. Dengan serangan yang sangat mematikan yang membuat orang menjadi lemas dan malas hingga mati. Hanya dengan bersatu mereka dapat menyerang pasukan Mageria.\n\nWahai pasukan UNIX! Ayo kita bersatu melawan kerajaan Mageria dan buktikan kalau kita kerajaan terkuat! Jangan mau kalah, sejarah kita selama lebih dari 300 jam melewati berbagai rintangan tidak boleh menjadi sia-sia! BERSATULAH UNIX! JAYALAH UNIX!\nHanya dengan 30 orang bersemangat kita dapat menahan kekuatan kerajaan Mageria.\n\nAyooo baru $count orang yang berkumpul!!!\n\nUNIX HARUS MENANG",
        "\"Ayo kita runtuhkan tembok itu!\" kata Ucup saat memimpin pasukan Bacaman, \"Jangan biarkan pasukan mereka membuat retak persaudaraan kita!\" Pasukan Bacaman bersama pasukan Camen dan Camedik pun bergerak menyerbu tembok besar itu dengan penuh semangat. Pertempuran itu tidaklah menyenangkan bagi siapapun, sudah terlalu banyak berjatuhan korban jiwa. Tetapi, apabila mereka berhenti bertempur, mungkin hasilnya akan lebih menyeramkan.\n\nTanpa lelah merekapun bertempur siang malam, demi membela kekeluargaan yang selama ini diajarkan oleh para Pendiklat. Seluruh interupsi yang dicanangkan oleh mereka menjadi bekal untuk menghadapi musuh dari kerajaan Mageria.\n\nSatu minggu terlewati, semakin banyak korban berguguran dari kedua belah pihak. Mereka telah berusaha semaksimal mungkin untuk membela kekeluargaan mereka. Mengingat hal tersebut, Ucup, Ridwan, dan Astha sebagai panglima dari Bacaman, Camedik, dan Camen, memutuskan untuk melakukan perundingan dengan pihak kerajaan Mageria. Setelah melalui beberapa surat kaleng, akhirnya kerajaan Mageria mau merundingkan gencatan senjata. Perundingan ini bertempat di basecamp UNIX 2017 di Kota Bandung.\n\nTibalah hari perundingan yang bersejarah ini. Masyarakat dan wartawan menyaksikan perundingan itu dengan penuh harap. Karpet merah digelar di beranda dan peniup terompet pun berjejer di samping karpet merah. Kedua belah pihak berjalan di atas karpet merah dengan penuh khidmat ke dalam ruangan. Sesampainya mereka di dalam ruangan, ternyata..\n\nBASECAMP NYA GAK KUORUM!!\n\nDengan penuh amarah, kedua belah pihak memutuskan untuk melanjutkan pertumpahan darah.\n\nGak mau perang kan guys? Makanya dateng ke basecamp biar kuorum!"
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
/*function pushToAllIndividuals($app, $messageBuilder) {
    $db = $app->db;
    $q = "SELECT `mid` FROM `Users`";
    $stmt = $db->prepare($q);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /** @var AMQPStreamConnection $amqp *
    $amqp = $app->amqp;
    $channel = $amqp->channel();
    $channel->queue_declare("osjurbot-line-queue", false, true, false, false);

    $arr = array();

    foreach($results as $row) {
        $payload = array(
            "mid" => $row['mid'],
            "msg" => serialize($messageBuilder)
        );
        array_push($arr, $payload);
    }

    $message = new AMQPMessage(json_encode($arr));
    $channel->basic_publish($message, '', 'osjurbot-line-queue');

    $channel->close();
    $amqp->close();
}*/

/** @var \LINE\LINEBot $bot */
function pushTextToAllIndividuals($app, $text) {
    $db = $app->db;
    $q = "SELECT * FROM `Users`";
    $stmt = $db->prepare($q);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /** @var AMQPStreamConnection $amqp */
    $amqp = $app->amqp;
    $channel = $amqp->channel();
    $channel->queue_declare("osjurbot-line-queue", false, true, false, false);

    $arr = array();

    foreach($results as $row) {
        $payload = array(
            "mid" => $row['mid'],
            "txt" => serialize(str_replace(array("{nama}", "{nim}", "{count}"), array($row['name'], $row['nim'], $row['count']), $text))
        );
        array_push($arr, $payload);
    }

    $message = new AMQPMessage(json_encode($arr));
    $channel->basic_publish($message, '', 'osjurbot-line-queue');

    $channel->close();
    $amqp->close();
}

/** @var \LINE\LINEBot $bot */
function pushTextToNIM($app, $text, array $nims) {
    $db = $app->db;

    /** @var AMQPStreamConnection $amqp */
    $amqp = $app->amqp;
    $channel = $amqp->channel();
    $channel->queue_declare("osjurbot-line-queue", false, true, false, false);

    $arr = array();

    foreach ($nims as $nim) {
        $q = "SELECT * FROM `Users` WHERE `nim`=:nim";
        $stmt = $db->prepare($q);
        $stmt->execute(["nim" => $nim]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $payload = array(
            "mid" => $row['mid'],
            "txt" => serialize(str_replace(array("{nama}", "{nim}", "{count}"), array($row['name'], $row['nim'], $row['count']), $text))
        );
        array_push($arr, $payload);
    }

    $message = new AMQPMessage(json_encode($arr));
    $channel->basic_publish($message, '', 'osjurbot-line-queue');

    $channel->close();
    $amqp->close();
}

function getRandomBasecampMoveMeme() {
    $messageBuilder = new MultiMessageBuilder();
    $messageBuilder->add(new ImagemapMessageBuilder(
        BASE_URL."/static/basecamp_move_".rand(1, 3),
        "[BASECAMP PINDAH GUYS]",
        new BaseSizeBuilder(1040, 1040),
        [
            new ImagemapUriActionBuilder(
                'https://osjurbot.didithilmy.com',
                new AreaBuilder(0, 0, 1, 1)
            )
        ]
    ));
    $messageBuilder->add(new TextMessageBuilder("Jadi, per tanggal 10 Juli 2018, basecamp kita sudah pindah ke GMKI di Jl. Ir. H. Djuanda. Jangan salah basecamp ya!!"));
    $messageBuilder->add(new LocationMessageBuilder("Basecamp UNIX 2017", "GMKI Cabang Bandung, Jl. Ir. H.Djuanda, Lb. Siliwangi, Coblong, Kota Bandung, Jawa Barat 40132", -6.892697, 107.6108723));

    return $messageBuilder;
}