<?php

use Slim\Http\Request;
use Slim\Http\Response;

//Parameter
define("RESERVED_COUNT",15); //Orang yang terjadwal jika ada masalah pada ACTIVE_COUNT
define("ACTIVE_COUNT",45); //Orang yang akan dijadwalkan dataang
define("SAFE_COUNT",5); //Selisih antara jumlah yang ada dan kuorum yang masih aman
define("KUORUM",30); //Minimal orang yang ada

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
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

$app->get('/listBasecamp', function (Request $request, Response $response, array $args) use ($app) {
    //Get izin
    $sql = "SELECT `uid` FROM `Users_blacklist` WHERE Date(blacklist) >= CURRENT_DATE";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $izins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //count shuffled
    $sql="SELECT DISTINCT `uid` FROM `Users_shuffled`";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    $count = 0;
    $izinCount = 0;

    foreach ($users as $user) {
        if(!in_array($user['uid'],array_column($izins,'uid'))){
            $count++;
        }else {
            $izinCount++;
        }
    }

    //Ambil sebanyak yang dibutuhkan
    $takeLimit = RESERVED_COUNT + ACTIVE_COUNT + $izinCount;

    //Jika kurang orang, shuffle untuk menambahkan
    if($count < $takeLimit ){
        $app->subRequest('GET', '/shuffleUsers');
    }

    $sql = "SELECT DISTINCT `uid`,`name`,`nim` FROM `Users_shuffled` INNER JOIN `Users`ON Users_shuffled.uid = Users.id LIMIT " . $takeLimit;

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //Filter user yang gak bisa datang
    $results = array_filter($results, function ($item) use ($izins) {
        if (!in_array($item['uid'], array_column($izins,'uid'))) {
            return true;
        }else {
            return false;
        }
    });

    return $response->withJson($results);
});

$app->get('/shuffleUsers', function (Request $request, Response $response, array $args) {

    //get All user
    $sql="SELECT `id` FROM `Users`";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    shuffle($results);

    //Insert
    $sql = "INSERT INTO `Users_shuffled`(`uid`) VALUES (:uid)";

    $stmt = $db->prepare($sql);
    $stmt->bindParam("uid",$uid);

    foreach ($results as $result) {
        $uid = $result['id'];
        $stmt->execute();
    }
    return $response->withJson($results);


});

$app->post('/sampai', function (Request $request, Response $response, array $args) {

    $sql = "INSERT INTO `Current`(`uid`) VALUES (:uid)";
    $uid =  $request->getParam('uid');

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

$app->post('/pulang', function (Request $request, Response $response, array $args) {

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

$app->post('/tambahBlacklist', function (Request $request, Response $response, array $args) {
    //Tambah ke daftar

    $sql = "INSERT INTO `Users_blacklist`(`uid`, `reason`, `type`, `blacklist`, `length`) VALUES (:uid,:reason,:type,:blacklist,:length)";

    try {
        $db = $this->get("db");

        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $request->getParam('uid'),
                        ':reason' => $request->getParam('reason'),
                        ':type' => $request->getParam('type'),
                        ':blacklist' => $request->getParam('blacklist'),
                        ':length' => $request->getParam('length')]);

    } catch (PDOException $e) {
        $error = ['error' => ['text' => $e->getMessage()]];
        return $response->withJson($error);
    }

    //Cek apakah hari ini
    $today = date("Y-m-d");
    $izinDate = strtotime($request->getParam('blacklist'));
    $izinDate = date("Y-m-d",$izinDate);

    if($today == $izinDate){
        //get reserved

        //TODO push message via line

    }else {
        return $response->withJson(['sukses']);
    }

});
