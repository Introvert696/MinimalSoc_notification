<?php

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Lib\Timer;
use Workerman\Worker;

$connections = []; //сюда собираем конекшены


// Стартуем сервер
$worker = new Worker("websocket://127.0.0.1:27800");

$worker->onConnect = function ($con) use (&$connections) {
    $con->onWebSocketConnect = function ($con) use (&$connections) {
        $con->send("Connected");
        $connections[$con->id] = $con;
    };
};
$worker->onMessage = function ($con, $message) use (&$connections) {
    //распаковываем json
    $messageData = json_decode($message, true);

    //при подключении мы добавляюм user_id пользователя самой соц сети 
    if (isset($messageData['type'])) {
        if ($messageData["type"] == "connect") {
            $connections[$con->id]->user_id = $messageData['id'];
        } else if ($messageData['type'] == "message") {
            if (isset($messageData['to'])) {
                //отправка всем подключенным
                foreach ($connections as $c) {
                    if (isset($c->user_id)) {
                        if ($c->user_id == $messageData['to']) {
                            $response["type"] = "new message";
                            $response["from"] =  $connections[$con->id]->user_id;
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
    //удаляем соединение из списка
    unset($connections[$con->id]);
};
Worker::runAll();
