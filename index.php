<?php

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Lib\Timer;
use Workerman\Worker;

$connections = []; //сюда собираем конекшены


// Стартуем сервер
$worker = new Worker("websocket://127.0.0.1:27800");

$worker->onConnect = function ($con) use (&$connections) {
    $con->onWebSocketConnect = function ($con) use (&$connections) {
        $resp["type"] = "connect";
        $con->send(json_encode($resp));
        $connections[$con->id] = $con;
        print_r("Новый коннект - " . $con->id . "\n");
    };
};
$worker->onMessage = function ($con, $message) use (&$connections) {
    //распаковываем json
    $messageData = json_decode($message, true);
    print_r($message . "\n");
    //при подключении мы добавляюм user_id пользователя самой соц сети 
    if (isset($messageData['type'])) {
        if ($messageData["type"] == "connect") {
            $connections[$con->id]->user_id = $messageData['id'];
            print_r("Пользователь подключен, его user_id - " . $messageData['id'] . "\n");
        } else if ($messageData['type'] == "message") {
            if (isset($messageData['to'])) {
                //отправка всем подключенным
                foreach ($connections as $c) {
                    if (isset($c->user_id)) {
                        if ($c->user_id == $messageData['to']) {
                            $response["type"] = "message";
                            $response["from"] = $messageData['id'];
                            $c->send(json_encode($response));
                        }
                    }
                }
            }
        }
    }
};

$worker->onClose = function ($con) use (&$connections) {
    if (!isset($connections[$con->id])) {
        return;
    }
    print_r("Отключен - " . $con->id . "\n");
    //удаляем соединение из списка
    unset($connections[$con->id]);
};
Worker::runAll();
