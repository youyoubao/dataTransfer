<?php
include "Library/RabbitMq.php";
//从队列读出数据抛给main.php处理
// rabbitmq连接参数
$conn_args = array(
    'host'     => '',
    'port'     => '',
    'login'    => '',
    'password' => '',
    'vhost'    => '/',
);
$server = array(
    'ip'   => '127.0.0.1',
    'port' => 9501,
);

$mq = new Data\Library\RabbitMq();
$mq->reconnect($conn_args);

while (1) {
    $message = $mq->readQueue("data2data");
    if (empty($message)) {
        //队列中没有数据
        echo "\n empty queue";
        sleep(30);
        $mq->reconnect($conn_args); //重新连接rabbitmq
        continue;
    } else {
        $message = json_decode($message, true);
    }
    if (!modelIsExist($message['schemaName'], $message['tableName'])) {
        echo "\n 忽略数据: {$message['schemaName']}.{$message['tableName']} ";
    } else {
        //单独更新一条记录
        $tablename = $message['schemaName'] . '.' . $message['tableName'];
        $id        = $message['pkid'];
        $ret       = sendToServer("singleData-" . $tablename . "-" . $id);
        while ($ret != "OK") {
            echo "\n service is busy, sleep 5";
            sleep(5);
            $ret = sendToServer("singleData-" . $tablename . "-" . $id);
        }
        echo "\n 处理增量：" . $tablename;
    }
}

//判断表是否存在，如果表不存在，忽略数据
function modelIsExist($schema, $table)
{
    if (file_exists("./Models/{$schema}/{$table}.php")) {
        return true;
    } else {
        return false;
    }
}

function sendToServer($str)
{
    global $server;
    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
    if (!$client->connect($server['ip'], $server['port'], -1)) {
        exit("connect failed. Error: {$client->errCode}\n");
    }
    $client->send($str);
    $str = $client->recv();
    $client->close();
    return $str;
}
