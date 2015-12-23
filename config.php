<?php
$db = array(
    'read'  => array(
        'host' => '192.168.0.1',
        'user' => 'root',
        'pswd' => '111111',
    ),
    'write' => array(
        'host' => '192.168.0.2',
        'user' => 'root',
        'pswd' => '123123',
    ),
);

//格式：{需要处理的表名}=>{进程数量}
$setting = array(
    'usercenter.user' => 30,
);
