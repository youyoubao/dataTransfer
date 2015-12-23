<?php
$client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);

if (!$client->connect('172.17.16.102', 9501, -1)) {
    exit("connect failed. Error: {$client->errCode}\n");
}
$_GET['single']    = 1;
$_GET['tablename'] = 'productinfo.pd_info';
$_GET['id']        = 77565627;

// $_GET['reassign'] = 1;
//设置处理进程数量，需要手动修改config.php配置文件
if (isset($_GET['reassign']) && $_GET['reassign'] == 1) {
    $client->send("reassign");
    $str = $client->recv();
    $client->close();
    echo $str;
    die;
} elseif (isset($_GET['single']) && $_GET['single'] == 1) {
    //从队列读数据，单独更新一条记录
    $tablename = $_GET['tablename'];
    $id        = $_GET['id'];

    $client->send("singleData-" . $tablename . "-" . $id);
    $str = $client->recv();
    $client->close();
    echo $str;
    die;
} elseif (isset($_GET['setcurId'])) {
    //动态设置表的执行开始ID
    $tablename = $_GET['tablename'];
    $id        = $_GET['id'];
    $client->send("setCurID-" . $tablename . "-" . $id);
    $str = $client->recv();
    $client->close();
    echo $str;
    die;
}

$client->send("getinfo");
sleep(1);
$str = $client->recv();
$client->close();

$a         = explode("\n", $str);
$taskinfo  = json_decode($a['0'], true);
$tableinfo = json_decode($a['1'], true);
$inf       = json_decode($a['2'], true);

print_r($taskinfo);
print_r($tableinfo);
print_r($inf);
die;
?>
	<h1><center>数据迁移</center></h1>
	<h2>处理进度：</h2>
    <meta charset="utf8">
		<?php
echo "<div style='clear:both; height:40px; width:920px; border-bottom:1px solid #ccc; line-height:40px;'><div style='width:365px; float:left'>表名称</div>";
echo "<div style='width:235px; float:left'>处理进程数量</div>";
echo "<div style='width:100px; float:left'>最大ID</div>";
echo "<div style='width:100; float:left'>当前进度</div>";
echo "<div style='width:100; float:left'>状态</div></div>\n";
foreach ($tableinfo as $v) {
    echo "<div style='clear:both; height:40px; width:920px; border-bottom:1px solid #ccc; line-height:40px; ";
    $processNum = isset($taskinfo[$v['tablename']]) ? $taskinfo[$v['tablename']] : 0;
    if ($v['curId'] > $v['maxId']) {
        echo "color:#00f;";
    } else {
        echo "color:#f00;";
    }
    echo "'>";
    echo "<div style='width:400px; float:left'>{$v['tablename']}</div>";
    echo "<div style='width:200px; float:left'>{$processNum}</div>";
    echo "<div style='width:100px; float:left'>{$v['maxId']}</div>";
    echo "<div style='width:100px; float:left'>{$v['curId']}</div>";
    if ($v['maxId'] > 0) {
        echo "<div style='width:100px; float:left'>" . round((($v['curId'] - 1) / $v['maxId']) * 100, 2) . "%</div>\n";
    } else {
        echo "<div style='width:100px; float:left'>100%</div>\n";
    }
    echo "</div>";
}

?>