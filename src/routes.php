<?php

use OTPHP\TOTP;
use Slim\Http\Request;
use Slim\Http\Response;

//Parameter
define("RESERVED_COUNT",15); //Orang yang terjadwal jika ada masalah pada ACTIVE_COUNT
define("ACTIVE_COUNT",45); //Orang yang akan dijadwalkan dataang
define("SAFE_COUNT",5); //Selisih antara jumlah yang ada dan kuorum yang masih aman
define("KUORUM",30); //Minimal orang yang ada

define("SECRET","SECRETCODE");

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

    $sql = "INSERT INTO `Current`(`uid`) VALUES (:uid)";
    $uid =  $request->getParam('uid');
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
        $stmt->execute([':uid' => $uid]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    // Hapus dari yang wajib datang (jika ada)
    $sql="SELECT * FROM `Users_shuffled` WHERE uid = :uid";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    if(count($results)>=1){
        $sql="DELETE FROM `Users_shuffled` WHERE uid = :uid LIMIT 1";

        try {
            $db = $this->get("db");

            $stmt = $db->prepare($sql);
            $stmt->execute([':uid' => $uid]);

        } catch (PDOException $e) {
            $error = ['error' => ['text' => $e->getMessage()]];
            return $response->withJson($error);
        }
    }

    return $response->withJson(['sukses']);

});

$app->post('/pulang', function (Request $request, Response $response, array $args) use ($app) {

    // Cek ada di tempat
    $sql = "SELECT * from Current where uid = :uid";
    $uid = $request->getParam('uid');

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid]);

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
        $sql="INSERT INTO `Log`(`uid`,`jam_masuk`) VALUES (:uid,:jam_masuk)";

        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid,
        ':jam_masuk' => $jamMasuk]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //delete current
    try {
        $sql="DELETE FROM `Current` WHERE uid = :uid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //add count
    try {
        $sql="UPDATE `Users` SET `count`=`count`+1 WHERE id = :uid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    return $response->withJson('sukses');

});

$app->get('/listCurrent', function (Request $request, Response $response, array $args) {

    $sql="SELECT `name`,`nim` FROM `Current` INNER JOIN `Users` ON Current.uid = Users.id";

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
