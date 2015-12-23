<?php
namespace Data\Models\Gongchanginfo;

class user2 extends \Data\Models\Table
{
    //源表名
    public $srcTable = "usercenter.user";
    //源表主键
    public $primaryKey = "uid";
    //目标数据库、表名
    public $descTable = "newUser.user";

    //传给callback函数的数据记录是经过处理、过滤后的数据
    public $callbackRow = 'desc'; //可选值 src/desc

    //数据转移时的字段对应关系
    //格式：{目标数据表的字段名} => {源表的字段名}
    public $fieldMap = array(
        'uid'      => 'uid',
        'username' => 'name',
        'sex'      => 'sex/getNewSex',
        'password' => 'passwd',
    );

    public function getNewSex($sex)
    {
        if ($sex == 1) {
            return '女';
        } elseif ($sex == 0) {
            return '男';
        } else {
            //源表中的这条记录会被忽略掉
            return false;
        }
    }

    /**
     * 数据迁移成功后的回调函数，可以在这里更新缓存、写队列等操作
     * @param  array   $row 数据库的记录
     * @return function      [description]
     */
    public function callback($row)
    {
        //这个函数是伪代码
        $memObj = new \Memcached();
        foreach ($row as $value) {
            $key = 'user_' . $value['uid'];
            $memObj->set($key, json_encode($value));
        }
    }
}
