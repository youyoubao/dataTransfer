<?php
namespace Data\Models\Gongchanginfo;

class user extends \Data\Models\Table
{
    //源表主键
    public $primaryKey = "uid";
    //目标数据库、表名
    public $descTable = "newUser.user";

    //数据转移时的字段对应关系
    //格式：{目标数据表的字段名} => {源表的字段名}
    public $fieldMap = array(
        'uid'      => 'uid',
        'username' => 'name',
        'sex'      => 'sex',
        'password' => 'passwd',
    );
}
