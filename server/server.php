<?php

//创建websocket服务器对象，监听0.0.0.0:9601端口
$ws = new swoole_websocket_server("0.0.0.0", 9601);

//设置异步任务的工作进程数量
$ws->set(array('task_worker_num' => 4));

// 设置redis全局变量
// $GLOBALS['redis'] = new Redis();
// $GLOBALS['redis']->connect('127.0.0.1', 6379);
// $GLOBALS['redisReady'] = false;

// 检查redis是否在线
function checkRedis(swoole_websocket_server $ws, bool $forceOutput = false) {
    global $redis, $redisReady;
    $redisRedayOrigin = $redisReady;
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->ping();
        $redisReady = true;
    } catch (\Exception $e) {
        $redisReady = false;
    }
    if ($redisReady != $redisRedayOrigin || $forceOutput) {
        $status = $redisReady ? 'On' : 'Off';
        $data = ['message' => "Log server status $status"];
        setTask($ws, $data);
    }
}

function setTask(swoole_websocket_server $ws, array $data) {
    $data = serialize($data);
    $task_id = $ws->task($data);
    echo "Dispath AsyncTask: id=$task_id\n";
}

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
    global $redis, $redisReady;
    checkRedis($ws, true);
    // var_dump($request->fd, $request->get, $request->server);
    if ($redisReady) {
        $messageList = $redis->lrange("swoole-chatroom-histories", -100, -1);
        if ($messageList) {
            try {
                $messageComposite = implode("<br />", $messageList);
                $data = ['message' => "Histories:<br />$messageComposite\n"];
                $data = json_encode($data);
                $ws->push($request->fd, $data);
            } catch (\Exception $e) {
            }
        }
    }
    $data = ['message' => "Hello, welcome!\n"];
    $data = json_encode($data);
    $ws->push($request->fd, $data);
    $data = ['message' => "client $request->fd come in!\n"];
    setTask($ws, $data);
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
    global $redis, $redisReady;
    checkRedis($ws);
    echo "Message: {$frame->data}\n";
    $rawData = json_decode($frame->data, true);
    print_r($rawData);
    if ($redisReady) {
        try {
            $redis->rpush("swoole-chatroom-histories", $rawData['message']);
        } catch (\Exception $e) {
        }
    }
    setTask($ws, $rawData);
});

//处理异步任务
$ws->on('task', function ($serv, $task_id, $from_id, $data) {
    echo "New AsyncTask[id=$task_id]".PHP_EOL;
    $data = json_encode(unserialize($data));
    foreach($serv->connections as $fd) {
        $serv->push($fd, $data);
    }
    //返回任务执行的结果
    $serv->finish("$data -> OK");
});

//处理异步任务的结果
$ws->on('finish', function ($serv, $task_id, $data) {
    echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;
});

//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
});

$ws->start();