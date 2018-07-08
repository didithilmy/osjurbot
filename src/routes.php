<?php

use OTPHP\TOTP;
use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/token', function (Request $request, Response $response, array $args) {
    $totp = TOTP::create(
        SECRET,
        300 // 5 menit
    );
    $totp->setLabel('SPARTA HMIF');

    return $response->withJson($totp->getQrCodeUri());
});

$app->post('/addNama', function (Request $request, Response $response, array $args) {

    $sql="INSERT INTO `Users`(`name`, `mid`, `nim`) VALUES (:name,:mid,:nim)";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':name' => $request->getParam('name'),
        ':mid' => $request->getParam('mid'),
        ':nim' => $request->getParam('nim')]);

        return $response->withJson(['sukses']);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }
});

$app->post('/sampai', function (Request $request, Response $response, array $args) {

    $sql = "INSERT INTO `Current`(`nim`, `jam_masuk`) VALUES (:nim, :jam_masuk)";
    $nim =  $request->getParam('nim');
    $token = $request->getParam('token');

    $totp = TOTP::create(
        SECRET,
        300 // 5 menit
    );
    $totp->setLabel('SPARTA HMIF');

    if(!$totp->verify($token)){
        $error = ['error' => ['text' => "Token invalid"]];
        return $response->withJson($error);
    }

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':nim' => $nim]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    return $response->withJson(['sukses' => true]);

});

$app->post('/pulang', function (Request $request, Response $response, array $args) use ($app) {

    // Cek ada di tempat
    $sql = "SELECT * from Current where nim = :nim";
    $nim = $request->getParam('nim');

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':nim' => $nim, ':jam_masuk' => date("Y-m-d H:i:s")]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);


    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    if(count($result)!=1){
        $error = ['error' => ['text' => "Belum masuk"]];
        return $response->withJson($error);
    }

    //Cek kuorum
    $res = $app->subRequest('GET', '/listCurrent');
    $count = json_decode($res->getBody(),true)['count'];

    if($count <= KUORUM){
        $error = ['error' => ['text' => "Kuorum tidak tercapai"]];
        return $response->withJson($error);
    }elseif ($count < (KUORUM + SAFE_COUNT)) {
        // TODO give warning
    }

    //Insert log
    $jamMasuk = $result[0]['jam_masuk'];

    try {
        $sql="INSERT INTO `Log`(`nim`,`jam_masuk`, `jam_keluar`) VALUES (:nim,:jam_masuk, :jam_keluar)";

        $stmt = $db->prepare($sql);
        $stmt->execute([':nim' => $nim,
            ':jam_masuk' => $jamMasuk,
            ':jam_keluar' => date("Y-m-d H:i:s")]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //delete current
    try {
        $sql="DELETE FROM `Current` WHERE nim = :nim";
        $stmt = $db->prepare($sql);
        $stmt->execute([':nim' => $nim]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //add count
    try {
        $sql="UPDATE `Users` SET `count`=`count`+1 WHERE id = :nim";
        $stmt = $db->prepare($sql);
        $stmt->execute([':nim' => $nim]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    return $response->withJson('sukses');

});

$app->get('/listCurrent', function (Request $request, Response $response, array $args) {

    $sql="SELECT `name`,`nim` FROM `Current` INNER JOIN `Users` ON Current.nim = Users.nim";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = count($result);

        return $response->withJson(['count' => $count,
        'users' => $result]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

});

$app->get('/user/associateManual', function (Request $request, Response $response, array $args) {
    $userId = $request->getParam("userId");
    return $response->withRedirect(INA_LOGIN_URL . "/cas/login?service=" . rawurlencode(BASE_URL . "/user/associate/propagate?userId=".$userId));
});

$app->get('/user/associate', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'assoc.phtml', array("ina_url" => INA_LOGIN_URL, "base_url" => BASE_URL));
});

$app->get('/user/associate/propagate', function (Request $request, Response $response, array $args) {
    $ticket = $request->getParam('ticket');
    $userId = $request->getParam("userId");

    $url = "https://login.itb.ac.id/cas/serviceValidate?ticket=$ticket&service=".rawurlencode(BASE_URL . "/user/associate/propagate?userId=".rawurlencode($userId));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    $raw = curl_exec($ch);

    $xml = new \SimpleXMLElement($raw);
    $xml->registerXPathNamespace('cas', 'http://www.yale.edu/tp/cas');

    foreach($xml->xpath('//cas:authenticationSuccess') as $event) {
        $mhsName = (string) ($event->xpath('//cas:attributes/cas:cn')[0]);
        $mhsNIM = (string) ($event->xpath('//cas:attributes/cas:itbNIM')[0]);
    }

    try {
        $db = $this->get("db");

        // Checks if NIM is authorized
        $q = "SELECT nim FROM peserta WHERE nim=:nim";
        $stmt = $db->prepare($q);
        $stmt->execute([':nim' => $mhsNIM]);

        if($stmt->rowCount() == 0) {
            // Unauthorized
            return $this->renderer->render($response, 'propagate_failed.phtml', array("ina_url" => INA_LOGIN_URL, "base_url" => BASE_URL, "error" => "NIM Anda tidak terdaftar sebagai peserta SPARTA 2017."));
        }

        // Inserts to table
        $q = "SELECT mid FROM Users WHERE nim=:nim";

        $stmt = $db->prepare($q);
        $stmt->execute([':nim' => $mhsNIM]);

        if($stmt->rowCount() == 0) {
            $ins = "INSERT INTO `Users`(`name`, `mid`, `nim`, `count`) VALUES (:name,:mid,:nim, 0)";
            $msg = "Halo, $mhsName ($mhsNIM)!";

            $sti = $db->prepare($ins);
            $sti->execute([':mid' => $userId, ':name' => $mhsName, ':nim' => $mhsNIM]);
        } else {
            $ins = "UPDATE `Users` SET `mid`=:mid WHERE `nim`=:nim";
            $msg = "Akun ini sekarang dihubungkan ke $mhsName ($mhsNIM).";

            $sti = $db->prepare($ins);
            $sti->execute([':mid' => $userId, ':nim' => $mhsNIM]);
        }



        /** @var \LINE\LINEBot $bot */
        $bot = $this->bot;
        $bot->pushMessage($userId, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($msg));

        return $this->renderer->render($response, 'propagate.phtml', array("ina_url" => INA_LOGIN_URL, "base_url" => BASE_URL));
    } catch (PDOException $e) {
        return $this->renderer->render($response, 'propagate_failed.phtml', array("ina_url" => INA_LOGIN_URL, "base_url" => BASE_URL, "error" => $e->getMessage()));
    }

    //return $response->withJson(array('nama' => $mhsName, 'nim' => $mhsNIM));
});